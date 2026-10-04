# Pitchwise — guide for players

Pitchwise listens while you play from sheet music and tells you, note by note, whether you were **in tune**, **too high** or **too low**. While you play, a tuner dial shows how close you are to the note under the cursor. After each page and at the end of the piece you get a result. It is made with string players in mind, and works for any instrument or voice that plays one note at a time.

Everything happens in your browser. Your sound is never recorded or sent anywhere; only the result for each note is saved.

## What you need

- A computer with Chrome, Firefox, Safari or Edge, and a microphone (the built-in one is fine).
- A quiet room. Background music or talking confuses the listening.
- The address of your Pitchwise site and a login. (Someone has to install it first; the developer README explains how.)

## Playing a piece

1. Log in and open **Pieces**. Pick one and press **▶ Practise**.
2. Check the **settings** on the right:
   - **Tempo**: crotchets (quarter notes) per minute. Start slower than you think.
   - **Counts as in tune within ±30 cents**: how strict the check is (see below).
   - **Concert A**: 440 Hz, or 442/443 if your orchestra tunes higher.
   - **From page / To page**: practise just one page if you like.
3. Press **Start**. The first time, your browser asks to use the microphone: allow it.
4. You hear one bar of clicks and see a big countdown. Then the cursor moves through the score at your tempo. **Play along with the cursor.**
5. While you play:
   - The **dial** shows the note you should be playing and where you are. The needle in the shaded green band means in tune. The words under it say *In tune*, *Too high ↑*, *Too low ↓* or *Wrong note* and by how much.
   - Each note in the score changes colour once it is over:

     | Colour | Meaning |
     |---|---|
     | green | in tune |
     | red | too high (sharp) |
     | blue | too low (flat) |
     | purple | wrong note (more than a quarter tone away) |
     | grey outline | missed (nothing clear was heard) |

   - The small counters show how many notes so far were in tune, too high, too low, wrong or missed.
6. At the end of each page a short message shows that page's result. At the end of the piece the **Result** panel appears: your score in %, the bars to practise, and the notes that were furthest off. **Open the saved report** shows every note.

Press **Stop** at any time; the notes you played so far are still counted.

## How strict is "in tune"?

Pitch differences are measured in **cents**: 100 cents is one semitone. By default a note counts as in tune if it is within **30 cents** (about a third of a semitone) of the written note. Between 30 and 50 cents it is *too high* or *too low*. More than 50 cents away, you are closer to the next note than to the right one, so it counts as a *wrong note*.

Want it stricter? Set 10 or 15 cents. Just starting out? 40 is kind.

You can also choose **Hz** instead of cents. Be careful: a fixed number of Hz is very loose on low notes and strict on high ones. With ±30 Hz, on the open G string even an A counts as "in tune", while high on the E string ±30 Hz is stricter than ±30 cents. The settings panel shows what your choice means on the G and E strings. For fair results across the whole instrument, keep cents.

## The tuner

**Tuner** in the top menu is a plain tuner without sheet music. Choose your instrument to see its open strings; the dial compares you to whichever note you are closest to. It uses the same concert A and strictness as the player.

## Your progress

**Dashboard** shows:

- **In tune per run**: your score for every run over time. Click a point to open that run.
- **Your intonation by note**: for each note, whether you tend to play it sharp (bar above zero) or flat (below). Many violinists, for example, play C♯ and F♯ a little high. These numbers are updated every few minutes.
- **Trouble bars**: the bars with the lowest score over your last 20 runs.
- **Recent runs**.

## Your own music

**Pieces → Upload MusicXML** accepts `.musicxml`, `.xml` or `.mxl` files. Most notation programs export these: in MuseScore, *File → Export → MusicXML*. Use music that is out of copyright or that you wrote or typed in yourself. Your uploads are private to you.

Only the **top melody line of the first instrument** is checked, one note at a time. Chords and double stops count their first note only; grace notes are skipped; repeats (도돌이표) and numbered endings are played out, so the notes are checked each time round; D.C., D.S., Fine and coda jumps are followed too. Simple pieces and studies work best.

## If something seems off

| What you see | What to try |
|---|---|
| "Microphone access was blocked" | Click the lock or camera icon in the address bar, allow the microphone, press Start again. |
| Many notes are *missed* | Play closer to the microphone or louder. Under *Timing and microphone*, set *Ignore sounds quieter than* to −40 dB. |
| Notes are judged against the previous note, or one note late | Under *Timing and microphone*, raise *Input delay* (try 120, then 150 ms). If they seem early, lower it. |
| Everything is slightly sharp or flat | Check *Concert A*. Tune your instrument with the Tuner page first. |
| Lots of *wrong notes* when you were sure you played well | Check the tempo: if you fall behind the cursor, the notes are judged against the wrong ones. Slow down. |
| A yellow message about "melody notes" | The colours may land on the wrong noteheads for that score. The results themselves are still right. |
| Results not saved | A yellow message explains why; the result shown is from your browser only. Ask whoever runs the site. |

Switching to another browser tab while playing pauses the listening, so stay on the player page during a run.
