group "default" {
  targets = ["plugins"]
}

target "plugins" {
  context = "."
  dockerfile = "Dockerfile.plugins"
  tags = ["ghcr.io/mahn-ke/ttrss-by-vincent/plugins:latest"]
  platforms = ["linux/amd64"]
  labels = {
    "org.opencontainers.image.source" = "https://github.com/mahn-ke/ttrss-by-vincent"
  }
}
