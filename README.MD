# ttrss.by.vincent.mahn.ke

Hosts the container serving [ttrss.by.vincent.mahn.ke](https://ttrss.by.vincent.mahn.ke).

## Plugin image

The shared deployment workflow checks out plugin submodules and builds
`docker-bake.hcl`, publishing `ghcr.io/mahn-ke/ttrss-by-vincent/plugins:latest`.
The initialization container copies the baked-in plugins into the existing
plugin volume; the app waits for that copy to finish. No plugin files are
mounted from the runner checkout, and database/application volumes are retained.

Deploy once to replace the old bind-mounted initialization container.
For local builds, first run `git submodule update --init --recursive`, then
`docker buildx bake --file docker-bake.hcl --load`.

## Snoozing Gotify notifications

In TT-RSS, open **Preferences → Feeds**, edit a feed, and find **Gotify
Notifications → Snooze notifications**. Select **1, 2, 7, 14, or 30 days**,
then save the feed. The editor displays the current expiry in UTC.

Snoozing affects only that feed's Gotify notifications, including explicit
Gotify filter actions. Feed updates and unread status are unchanged. Notifications
resume automatically at expiry; suppressed notifications are not replayed.
**Resume now (clear snooze)** cancels a snooze without changing the existing
Enable checkbox or filter configuration. **Keep current snooze** preserves the
expiry when saving other feed settings. Choosing a duration restarts the snooze
from the time you save.

The upstream Gotify plugin remains a pinned submodule. The plugin image applies
[our snooze patch](patches/gotify-snooze.patch) during its build; an incompatible
upstream update fails the build rather than silently dropping the customization.
Deploy the rebuilt image to expose these controls in the live web UI.

To validate the patch, UI, and both notification paths without sending messages:

```sh
docker build -f Dockerfile.plugins -t ttrss-plugins-snooze-test .
plugin_test_file="$(mktemp /tmp/ttrss-gotify-test.XXXXXX)"
docker run --rm --entrypoint cat ttrss-plugins-snooze-test \
  /plugins.local/gotify_notifications/init.php > "$plugin_test_file"
docker run --rm \
  -v "$PWD:/work:ro" \
  -v "$plugin_test_file:/work/plugins.local/gotify_notifications/init.php:ro" \
  -w /work php:8.3-cli php tests/gotify-snooze.php
rm "$plugin_test_file"
```