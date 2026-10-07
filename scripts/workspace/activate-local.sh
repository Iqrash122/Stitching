# Source this file in the current ChatGPT session; it does not change system settings.
STITCHING_RUNTIME="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)/runtime/bin"
if [ ! -x "$STITCHING_RUNTIME/php" ]; then
    printf 'Local PHP runtime missing. Use the Docker workspace instructions.\n' >&2
    return 1
fi
export PATH="$STITCHING_RUNTIME:$PATH"
unset STITCHING_RUNTIME
