provider "gotify" {
  endpoint = "https://notifications.by.vincent.mahn.ke"
}

# Create a Gotify app...
resource "gotify_application" "ttrss" {
  name        = "ttrss"
  description = "Tiny Tiny RSS instance at https://ttrss.by.vincent.mahn.ke"
}