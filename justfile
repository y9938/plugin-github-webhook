name := "GithubWebhook"
title := "Github Webhook"
version := "1.1.1"
compatible_version := "1.2.16"
repo := "y9938/plugin-github-webhook"
plugin_key := "github-webhook"
website_repo := "kanboard/website"
website_dir := env('WEBSITE_DIR', env('HOME', '') + '/diff_repo_external/kanboard-website')

# Build archive and publish GitHub release with uploaded zip asset
release notes:
    @git tag -a "v{{version}}" -m "Plugin {{title}} v{{version}}"
    @git push origin "v{{version}}"
    @git archive "v{{version}}" --format=zip --prefix={{name}}/ -o {{name}}-{{version}}.zip
    @gh release create "v{{version}}" "{{name}}-{{version}}.zip" --repo {{repo}} --verify-tag --title "Plugin GitHub Webhook v{{version}}" --notes "{{notes}}"

# Create a pull request on the website repository
[script]
plugins-pr:
    set -euo pipefail
    fork_owner="$(gh api user --jq .login)"
    if [ ! -d "{{website_dir}}/.git" ]; then
      git clone "git@github.com:{{website_repo}}.git" "{{website_dir}}"
    fi
    cd "{{website_dir}}"

    if ! git remote get-url upstream >/dev/null 2>&1; then
      gh repo fork --remote
    fi

    git fetch upstream
    branch="chore/update-github-webhook-v{{version}}"
    git checkout -B "$branch" upstream/main
    tmp="$(mktemp)"
    jq --indent 4 \
      --arg compatible_version ">={{compatible_version}}" \
      --arg version "{{version}}" \
      --arg title "{{title}}" \
      --arg date "$(date +%F)" \
      --arg repo "{{repo}}" \
      --arg name "{{name}}" \
      --arg plugin_key "{{plugin_key}}" \
      '.[$plugin_key] = {
        "author": "Frédéric Guillot",
        "compatible_version": $compatible_version,
        "description": "Connect [GitHub](https://github.com) webhook events to Kanboard Automatic Actions. \n \n ⚠️&nbsp; This plugin connects to a third party service.",
        "download": ("https://github.com/" + $repo + "/releases/download/v" + $version + "/" + $name + "-" + $version + ".zip"),
        "has_hooks": true,
        "has_overrides": false,
        "has_schema": false,
        "homepage": ("https://github.com/" + $repo),
        "is_type": "connector",
        "last_updated": $date,
        "license": "MIT",
        "readme": ("https://raw.githubusercontent.com/" + $repo + "/main/README.md"),
        "remote_install": true,
        "title": $title,
        "version": $version
      }' plugins.json > "$tmp"
    mv "$tmp" plugins.json
    if git diff --quiet -- plugins.json; then
      echo "No changes in plugins.json"
      exit 0
    fi
    git add plugins.json
    git commit -m "Update github-webhook plugin to v{{version}}"
    git push -u origin "$branch"
    gh pr create \
      --repo "{{website_repo}}" \
      --head "${fork_owner}:${branch}" \
      --title "Update plugins.json ({{plugin_key}} v{{version}})" \
      --body "Update plugin metadata."

# Run release and plugins-pr recipes in sequence
all notes:
    @just release "{{notes}}"
    @just plugins-pr

# Sync compatible version from justfile to project files
sync-compatible-version:
    @sed -i -E "/function getCompatibleVersion\\(\\)/,/^    }/ s|(return ')[^']*(';)|\1>={{compatible_version}}\2|" Plugin.php
    @sed -i -E "0,/^- Kanboard .*/s//- Kanboard >= {{compatible_version}}/" README.md
    @echo "Updated compatibility version to {{compatible_version}}"

# Sync plugin version to Plugin.php
sync-plugin-version:
    @sed -i -E "/function getPluginVersion\\(\\)/,/^    }/ s|(return ')[^']*(';)|\1{{version}}\2|" Plugin.php
    @echo "Updated plugin version to {{version}}"
