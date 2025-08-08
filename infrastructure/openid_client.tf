variable "CLIENT_ID" {
  description = "Keycloak OpenID Client ID for the service"
  type        = string
  sensitive   = true
}

resource "keycloak_openid_client" "openid_client" {
  realm_id  = data.keycloak_realm.sso_by_vincent_mahn_ke.id
  client_id = var.CLIENT_ID

  name    = local.subdomain_label
  enabled = true

  access_type = "CONFIDENTIAL"
  valid_redirect_uris = [
    "https://${local.subdomain_label}.by.vincent.mahn.ke/*"
  ]
  use_refresh_tokens = false
}
