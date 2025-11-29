#!/bin/bash

set -eo pipefail

help() {
    echo "Usage: ./deploy-wp.sh [ -s | --slug ] Plugin slug.
                      [ -v | --version ] Plugin version.
                      [ -d | --dir ] Optional source files directory (defaults to freshly built dist/).
                      [ -u | --username ] WP.org username.
                      [ -p | --password ] WP.org password.
                      [ -m | --message ] Commit message.
                      [ -h | --help  ]"
    exit 2
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
DIST_DIR="${PROJECT_ROOT}/dist"
PACKAGE_MANAGER=""
AUTO_SVN_SRC=true

detect_package_manager() {
    if command -v yarn >/dev/null 2>&1; then
        PACKAGE_MANAGER="yarn"
    elif command -v npm >/dev/null 2>&1; then
        PACKAGE_MANAGER="npm"
    else
        echo "✗ Neither yarn nor npm found. Install one to run the build."
        exit 1
    fi
}

run_build() {
    detect_package_manager

    echo "➤ Running project build with ${PACKAGE_MANAGER}..."
    if [ "$PACKAGE_MANAGER" = "yarn" ]; then
        (cd "$PROJECT_ROOT" && yarn build)
    else
        (cd "$PROJECT_ROOT" && npm run build)
    fi

    echo "➤ Running makepot..."
    composer makepot
}

prepare_dist_dir() {
    echo "➤ Preparing dist directory..."
    rm -rf "$DIST_DIR"
    mkdir -p "$DIST_DIR"

    local rsync_includes=(
        "--include" "assets/"
        "--include" "assets/**"
        "--include" "includes/"
        "--include" "includes/**"
        "--include" "legacy/"
        "--include" "legacy/**"
        "--include" "languages/"
        "--include" "languages/**"
        "--include" "index.php"
        "--include" "wcsdm.php"
        "--include" "README.txt"
        "--exclude" "*"
    )

    rsync -a --delete "${rsync_includes[@]}" "$PROJECT_ROOT/" "$DIST_DIR/"
}

while [ "$1" ]; do
    case "$1" in
    -s | --slug)
        SLUG="$2"
        shift 2
        ;;
    -v | --version)
        VERSION="$2"
        shift 2
        ;;
    -d | --dir)
        SVN_SRC_DIR="$2"
        AUTO_SVN_SRC=false
        shift 2
        ;;
    -u | --username)
        SVN_USERNAME="$2"
        shift 2
        ;;
    -p | --password)
        SVN_PASSWORD="$2"
        shift 2
        ;;
    -m | --message)
        COMMIT_MSG="$2"
        shift 2
        ;;
    -h | --help)
        help
        ;;
    --)
        shift
        break
        ;;
    *)
        echo "Unexpected option: $1"
        help
        ;;
    esac
done

while [[ -z "$SLUG" ]]; do
    read -p 'Plugin slug: ' SLUG
done

while [[ -z "$VERSION" ]]; do
    read -p 'Plugin version: ' VERSION
done

while [[ -z "$SVN_USERNAME" ]]; do
    read -p 'SVN username: ' SVN_USERNAME
done

while [[ -z "$SVN_PASSWORD" ]]; do
    read -s -p 'SVN password: ' SVN_PASSWORD
    echo
done

while [[ -z "$COMMIT_MSG" ]]; do
    default_msg="Update to version $VERSION"
    read -e -p "Commit message [${default_msg}]: " COMMIT_MSG
    COMMIT_MSG="${COMMIT_MSG:-$default_msg}"
done

run_build
prepare_dist_dir

if [ "$AUTO_SVN_SRC" = true ]; then
    SVN_SRC_DIR="$DIST_DIR"
elif [ ! -d "$SVN_SRC_DIR" ]; then
    echo "✗ Provided source directory '$SVN_SRC_DIR' does not exist."
    exit 1
fi

SVN_SRC_DIR=$(cd "$SVN_SRC_DIR" && pwd)

SVN_URL="https://plugins.svn.wordpress.org/${SLUG}/"
SVN_LOCAL_DIR="${HOME}/.deploy-wp/plugins/${SLUG}"

echo "Plugin slug: $SLUG"
echo "Plugin version: $VERSION"
echo "SVN Source directory: $SVN_SRC_DIR"
echo "SVN username: $SVN_USERNAME"
echo "SVN password: *****"
echo "SVN URL: $SVN_URL"
echo "SVN local directory: $SVN_LOCAL_DIR"
echo "Commit message: $COMMIT_MSG"

# Checkout just trunk and assets for efficiency
# Tagging will be handled on the SVN level
echo "➤ Checking out .org repository..."
svn checkout --depth immediates "$SVN_URL" "$SVN_LOCAL_DIR"
cd "$SVN_LOCAL_DIR" || exit
svn update --set-depth infinity tags
svn update --set-depth infinity trunk

if [ -d "$SVN_LOCAL_DIR/tags/$VERSION" ]; then
    while true; do
        read -p "Tags version $VERSION is already exists. Do you want to override? " yn
        case $yn in
        [Yy]*) break ;;
        [Nn]*) exit ;;
        *) echo "Please answer yes or no." ;;
        esac
    done
fi

echo "➤ Copying files from source directory..."

# Copy from source directory to /trunk
# The --delete flag will delete anything in destination that no longer exists in source
rsync -rc "$SVN_SRC_DIR/" "$SVN_LOCAL_DIR/trunk/" --delete --delete-excluded

# Copy tag locally to make this a single commit
echo "➤ Copying tag..."
rsync -rc "$SVN_LOCAL_DIR/trunk/" "$SVN_LOCAL_DIR/tags/$VERSION/" --delete --delete-excluded

# Add everything and commit to SVN
# The force flag ensures we recurse into subdirectories even if they are already added
# Suppress stdout in favor of svn status later for readability
echo "➤ Staging changes..."
svn add . --force >/dev/null

# SVN delete all deleted files
# Also suppress stdout here
svn status | grep '^\!' | sed 's/! *//' | while read -r file; do
    svn rm "$file" >/dev/null
done

if [ -z "$(svn status)" ]; then
    echo "✗ Nothing to commit, working tree clean"
    exit 1
fi

if [[ -z "$COMMIT_MSG" ]]; then
    COMMIT_MSG="Update to version $VERSION"
fi

echo "➤ Committing changes..."
svn commit -m "$COMMIT_MSG" --no-auth-cache --non-interactive --username "$SVN_USERNAME" --password "$SVN_PASSWORD"

echo "✓ Plugin deployed!"
