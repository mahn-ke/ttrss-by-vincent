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