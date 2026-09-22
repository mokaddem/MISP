# The Analyst Assessment — slide deck

A 25-slide talk on MISP's analyst assessment system for CTI analysts and
SOC practitioners: what it is, why it exists, the signals it is built
from, how to configure it, and how to add a signal of your own.

```
deck.html         the deck — open it in a browser, that is all
deck.md           the same deck as Markdown (generated)
to-markdown.mjs   regenerates deck.md from deck.html
capture.mjs       re-takes the screenshots from a live MISP instance
img/              the screenshots
example/          the custom signal the last two slides demonstrate
```

## Presenting

Open `deck.html` in any browser. No build step, no server, no network —
it is one file plus images.

| Key | |
|---|---|
| `→` `←` `space` `PgDn` `PgUp` | move |
| `Home` `End` | first / last slide |
| `f` | fullscreen |
| `p` | toggle the stacked view (all slides, scrollable) |

Clicking the right half of a slide advances, the left half goes back.
The slide number is in the URL, so a reload keeps your place and you can
link to a slide: `deck.html#14`.

**PDF:** press `p`, then print. Choose landscape and *Background
graphics*. Each slide becomes one page.

## Editing

One slide is one `<section class="slide">`. Add one, delete one, reorder
them — the navigation, the slide counter and the Markdown export all
read the document, so nothing else needs changing.

The full class vocabulary is documented in a comment at the top of
`deck.html`. The short version:

```html
<section class="slide">
  <p class="kicker">Small label above the heading</p>
  <h2>The slide heading</h2>
  <p class="lead">One large opening sentence.</p>
  <p>Ordinary prose.</p>
  <p class="note">Small muted line, pinned to the bottom.</p>
</section>
```

Add `dense` to a slide's class when it carries a full table, and `code`
when it carries a listing — both only tighten the spacing.

Everything is sized in `cqw`, hundredths of the slide's own width, so a
slide scales as one piece on any screen. There are no magic pixel
values to keep in step.

## Markdown

```
node to-markdown.mjs
```

Reads `deck.html`, writes `deck.md`, slides separated by `---` — which
is what Marp, reveal-md, Slidev and Pandoc all read as a slide break.
No dependencies. It understands the vocabulary above; stay inside it
and an edit to the slides shows up in the Markdown without touching the
script.

`deck.md` is generated. Edit `deck.html`.

## The screenshots

Every image in `img/` is a real element clipped from a real page of a
running MISP instance in its dark theme, not a mock-up. The values are
this instance's own:

| Value | Reading |
|---|---|
| `8.8.8.8` | contested, quality 57 — a warninglist against eight reporting organisations |
| `google.com` | asserted benign, 37 |
| `45.155.205.233` | contested, 11 — by false-positive sightings rather than a warninglist |
| `27304b246c7d5b4e149124d5f93c5b01` | asserted threat, 19 |

The numbers were produced under the **Incident Response &
Investigation** profile, which was the one in force when they were
taken. Another profile gives other numbers — which is rather the point
of the talk.

To re-take them against your own instance:

```
node capture.mjs https://your-misp admin@example.test yourpassword
```

It needs Playwright; if it is not resolvable from this folder, point at
an install with `PLAYWRIGHT=/path/to/node_modules/playwright/index.mjs`.
Edit the `VALUES` and `PROFILE_ID` constants at the top to match what
your instance actually holds. Two images it does not produce are
`bench-score.png` and `bench-moved.png`: they only exist while a profile
is open in the editor with a value on the bench and a weight changed, so
they are cropped out of that screen by hand. `profiles-index.png` and
`editor-signals.png` come out as full pages and were cropped down.

## The example signal

`example/LifecycleLongevity.php` is the custom signal slides 23–24
demonstrate. It scores how long a value has been on record, which
nothing in the shipped catalogue does.

To run the demo live during the talk, drop it into the custom signals
directory of the instance you are presenting from:

```
cp example/LifecycleLongevity.php /var/www/MISP/app/Lib/ValueSignals/
```

The next page load discovers it: the profile editor's Signals section
grows a row tagged **custom** and **available**, with a form built from
the class's own `points_schema`. Tick it in a profile and it starts
emitting ledger rows. Delete the file to take it away again — nothing
else has to change, and no profile has to be edited first, because
discovery is not activation.

It is not installed by default, and it must not be committed into
`app/Lib/ValueSignals/`: that directory is expected to be empty in a
clean checkout.
