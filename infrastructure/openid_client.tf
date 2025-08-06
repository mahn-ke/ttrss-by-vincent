provider "keycloak" {
    url           = "https://sso.by.vincent.mahn.ke"
}

data "keycloak_realm" "sso_by_vincent_mahn_ke" {
  realm = "sso.by.vincent.mahn.ke"
}

resource "keycloak_openid_client" "openid_client" {
  realm_id            = data.keycloak_realm.sso_by_vincent_mahn_ke.id
  client_id           = "ttrss"

  name                = "Tiny Tiny RSS"
  enabled             = true

  access_type         = "CONFIDENTIAL"
  valid_redirect_uris = [
    "https://ttrss.by.vincent.mahn.ke/*"
  ]
  use_refresh_tokens  = false
}
