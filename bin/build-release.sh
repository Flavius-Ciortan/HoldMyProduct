#!/usr/bin/env bash
set -euo pipefail

plugin_root="$(cd "$(dirname "$0")/.." && pwd)"
version="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$plugin_root/HoldThisProduct.php" | head -n 1)"
release_dir="$plugin_root/release"
archive="$release_dir/hold-this-product-$version.zip"
stage_root="$(mktemp -d)"
stage_plugin="$stage_root/hold-this-product"

cleanup() {
	rm -rf "$stage_root"
}
trap cleanup EXIT

mkdir -p "$stage_plugin" "$release_dir"
rsync -a --exclude-from="$plugin_root/.distignore" "$plugin_root/" "$stage_plugin/"
mv "$stage_plugin/HoldThisProduct.php" "$stage_plugin/hold-this-product.php"

rm -f "$archive"
( cd "$stage_root" && zip -qr "$archive" hold-this-product )
printf '%s\n' "$archive"
