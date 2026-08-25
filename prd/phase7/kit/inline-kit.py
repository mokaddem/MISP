"""
Inline the mockup kit into a mockup source file and write the publishable
copy to prd/phase7/build/.

    python3 prd/phase7/kit/inline-kit.py prd/phase7/mockups/occurrences.html

The source file keeps the `<!-- vp-kit -->` marker and stays small enough to
read in a diff; the built copy carries the 812KB of MISP CSS and is what the
Artifact tool publishes. The build directory is not committed.
"""
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
PHASE7 = os.path.dirname(HERE)
KIT = os.path.join(HERE, 'mockup-kit.css')
BUILD = os.path.join(PHASE7, 'build')

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

    os.makedirs(BUILD, exist_ok=True)
    dst = os.path.join(BUILD, os.path.basename(src))
    with open(dst, 'w', encoding='utf-8') as handle:
        handle.write(out_text)

    print('%s -> %s (%.1f KB)'
          % (os.path.relpath(src), os.path.relpath(dst),
             os.path.getsize(dst) / 1024.0))
    print('publish that path with the Artifact tool')


main()
