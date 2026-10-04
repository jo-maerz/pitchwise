#!/bin/sh
# Recognition worker. Watches <spool>/in for PDFs, runs Audiveris on each, and leaves
# <spool>/out/<id>.mxl (success) or <spool>/out/<id>.failed (reason) for the Laravel queue job
# (app/Jobs/ConvertPdfScore.php, app/Services/Omr/OmrSpool.php).
#
# Also usable outside Docker, with Audiveris installed:
#   AUDIVERIS=audiveris OMR_SPOOL=storage/app/private/omr sh docker/omr/watch.sh
set -u

SPOOL="${OMR_SPOOL:-/spool}"
AUDIVERIS="${AUDIVERIS:-/opt/audiveris/bin/Audiveris}"
LIMIT="${OMR_TIMEOUT_SECONDS:-900}"

mkdir -p "$SPOOL/in" "$SPOOL/out" "$SPOOL/work"
chmod 777 "$SPOOL/in" "$SPOOL/out" 2>/dev/null || true
echo "omr: watching $SPOOL/in"

fail() { # id, reason
    printf '%s\n' "$2" > "$SPOOL/out/$1.failed.tmp"
    mv "$SPOOL/out/$1.failed.tmp" "$SPOOL/out/$1.failed"
    chmod 666 "$SPOOL/out/$1.failed"
}

# A reason a person can act on, taken from Audiveris's log; falls back to the generic text.
why() { # logfile, fallback
    if grep -q "Too large image" "$1"; then
        echo "The PDF pages are too large to read (Audiveris limit: 20 megapixels). Use A4 or Letter pages, or a lower scan resolution."
    elif grep -q "No system found" "$1"; then
        echo "No staves were found in this PDF. It may be a scan that is too faint or skewed, or not sheet music."
    else
        echo "$2"
    fi
}

# Run Audiveris on $pdf; extra arguments are the sheet (page) numbers to process, none = all.
audiveris() { # outdir [page...]
    outdir="$1"; shift
    pages=""
    [ "$#" -gt 0 ] && pages="-sheets $*"
    timeout "$LIMIT" "$AUDIVERIS" -batch -transcribe -export $pages -output "$outdir" -- "$pdf" >> "$log" 2>&1
}

# More than one movement comes out as name.mvt1.mxl, name.mvt2.mxl: take the first.
first_mxl() { find "$1" -name '*.mxl' 2>/dev/null | sort | head -n 1; }

convert() { # id
    id="$1"
    work="$SPOOL/work/$id"
    pdf="$work/$id.pdf"
    log="$work/log.txt"
    rm -rf "$work"; mkdir -p "$work"
    mv "$SPOOL/in/$id.pdf" "$pdf"
    echo "omr: $id started"

    audiveris "$work/out"
    mxl=$(first_mxl "$work/out")
    warn=""

    # One unreadable page makes Audiveris abandon the whole book (typically a nearly empty last page:
    # "No system found"). Run again on the pages it could read and tell the owner what was left out.
    if [ -z "$mxl" ]; then
        total=$(grep -o -E '[0-9]+ sheets? in ' "$log" | head -n 1 | grep -o -E '^[0-9]+')
        bad=$(grep -o -E 'Sheet [^ ]*#[0-9]+ flagged as invalid' "$log" | grep -o -E '#[0-9]+' | tr -d '#' | sort -un)
        if [ -n "${total:-}" ] && [ -n "$bad" ]; then
            good=""; n=1
            while [ "$n" -le "$total" ]; do
                echo "$bad" | grep -qx "$n" || good="$good $n"
                n=$((n + 1))
            done
            if [ -n "$good" ]; then
                echo "omr: $id retrying without page(s) $(echo $bad)"
                audiveris "$work/out2" $good
                mxl=$(first_mxl "$work/out2")
                [ -n "$mxl" ] && warn="Page $(echo $bad | tr ' ' ',') could not be read and was skipped, so the end of the score may be missing."
            fi
        fi
    fi

    if [ -n "$mxl" ]; then
        [ -n "$warn" ] && { printf '%s\n' "$warn" > "$SPOOL/out/$id.warn"; chmod 666 "$SPOOL/out/$id.warn"; }
        cp "$mxl" "$SPOOL/out/$id.mxl.tmp"
        chmod 666 "$SPOOL/out/$id.mxl.tmp"
        mv "$SPOOL/out/$id.mxl.tmp" "$SPOOL/out/$id.mxl"   # appears complete or not at all; .warn is written first
        echo "omr: $id done${warn:+ (with warning)}"
    else
        fail "$id" "$(why "$log" "Audiveris could not read any music in this PDF.")"
        echo "omr: $id failed"; tail -n 15 "$log"
    fi
    rm -rf "$work"
}

while true; do
    for pdf in "$SPOOL"/in/*.pdf; do
        [ -e "$pdf" ] || continue
        convert "$(basename "$pdf" .pdf)"
    done
    sleep 2
done
