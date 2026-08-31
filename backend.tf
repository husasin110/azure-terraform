terraform {
  backend "azurerm" {
    resource_group_name  = "rg-terraform-state-prod"
    storage_account_name = "sttfstate84729"
    container_name       = "tfstate"
    key                  = "enterprise-app.terraform.tfstate"
  }
}