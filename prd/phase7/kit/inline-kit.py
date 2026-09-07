"""
Inline the mockup kit into a mockup source file and write the publishable
copy to the `build/` beside its `mockups/`.

    python3 prd/phase7/kit/inline-kit.py prd/phase7/mockups/occurrences.html
    #   -> prd/phase7/build/occurrences.html
    python3 prd/phase7/kit/inline-kit.py \
        prd/analyst-profile/mockups/ledger-sheet.html
    #   -> prd/analyst-profile/build/ledger-sheet.html

The source file keeps the `<!-- vp-kit -->` marker and stays small enough to
read in a diff; the built copy carries the 812KB of MISP CSS and is what the
Artifact tool publishes. The build directory is not committed.

The output directory used to be `prd/phase7/build` unconditionally. It is
now the `build/` sibling of whatever directory the source is in, which is
the same path for every phase 7 mockup and lets phase 8b keep its
candidates under `prd/analyst-profile/` (09b-prototypes.md §1.3).
"""
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
KIT = os.path.join(HERE, 'mockup-kit.css')

MARKER = '<!-- vp-kit -->'


def main():
    if len(sys.argv) < 2:
        raise SystemExit(__doc__)
    src = os.path.abspath(sys.argv[1])
    text = open(src, encoding='utf-8').read()

    if MARKER not in text:
        raise SystemExit('!! %s has no %s marker' % (src, MARKER))
    if not os.path.exists(KIT):
        raise SystemExit('!! run prd/phase7/kit/build-kit.sh first')

    kit = open(KIT, encoding='utf-8').read()
    if '</style' in kit.lower():
        raise SystemExit('!! kit contains a </style> sequence')

    out_text = text.replace(MARKER, '<style>\n%s\n</style>' % kit, 1)

    # A published artifact may not reach any external host, and an artifact
    # that names a private dev instance leaks it to whoever it is shared with.
    for pattern, label in (
        (r'https?://(?!localhost)[^\s"\'()]+', 'external URL'),
        (r'https?://localhost', 'dev host'),
    ):
        hits = [h for h in re.findall(pattern, text)
                if 'getbootstrap.com' not in h and 'github.com' not in h]
        if hits:
            print('   note: %s in source: %s' % (label, hits[:3]))

    build = os.path.join(os.path.dirname(os.path.dirname(src)), 'build')
    os.makedirs(build, exist_ok=True)
    dst = os.path.join(build, os.path.basename(src))
    with open(dst, 'w', encoding='utf-8') as handle:
        handle.write(out_text)

    print('%s -> %s (%.1f KB)'
          % (os.path.relpath(src), os.path.relpath(dst),
             os.path.getsize(dst) / 1024.0))
    print('publish that path with the Artifact tool')


main()
