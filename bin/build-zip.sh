#!/bin/sh
# Builds dist/hardenly.zip for the WordPress.org Plugin Directory (files listed in .distignore are left out).
set -e
cd "$(dirname "$0")/.."
rm -rf dist && mkdir -p dist/hardenly
rsync -a --exclude-from=.distignore ./ dist/hardenly/
(cd dist && zip -qr hardenly.zip hardenly)
echo "dist/hardenly.zip"
(cd dist && unzip -l hardenly.zip | tail -n +4 | sed '$d' | sed '$d' | awk '{print $4}')
