terraform {
  backend "pg" {
  }
  required_providers {
    allinkl = {
      source = "ViMaSter/allinkl"
      version = "0.1.0"
    }
    keycloak = {
      source = "mrparkers/keycloak"
      version = "4.4.0"
    }
  }
}

provider "allinkl" {
    kas_auth_type = "plain"
}

resource "random_password" "ddns_password" {
    length  = 30
    special = false
}

locals {
  subdomain_label = replace(replace(basename(dirname(path.cwd)), "-by-vincent", ""), "-", ".")
}

resource "allinkl_ddns" "subdomain" {
    dyndns_comment   = local.subdomain_label
    dyndns_password  = random_password.ddns_password.result
    dyndns_zone      = "mahn.ke"
    dyndns_label     = "${local.subdomain_label}.by.vincent"
    dyndns_target_ip = "88.99.215.101"
}

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
