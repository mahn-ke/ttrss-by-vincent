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
