terraform {
  backend "azurerm" {
    resource_group_name  = "rg-terraform-state-prod"
    storage_account_name = "sttfstate84729" # Must match what you created in Step 1
    container_name       = "tfstate"
    key                  = "enterprise-app.terraform.tfstate"
  }
}