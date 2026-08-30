# 1. Log Analytics Workspace (Required for modern Application Insights)
resource "azurerm_log_analytics_workspace" "law" {
  name                = "law-enterprise-app-${random_integer.suffix.result}"
  location            = azurerm_resource_group.rg.location
  resource_group_name = azurerm_resource_group.rg.name
  sku                 = "PerGB2018"
  retention_in_days   = 30
}

# 2. Application Insights
resource "azurerm_application_insights" "appinsights" {
  name                = "appi-enterprise-app-${random_integer.suffix.result}"
  location            = azurerm_resource_group.rg.location
  resource_group_name = azurerm_resource_group.rg.name
  workspace_id        = azurerm_log_analytics_workspace.law.id
  application_type    = "web"
}

# 3. App Service Plan
resource "azurerm_service_plan" "asp" {
  name                = "asp-enterprise-app"
  location            = azurerm_resource_group.rg.location
  resource_group_name = azurerm_resource_group.rg.name
  os_type             = "Linux"
  sku_name            = "B1" # B1 is the lowest tier that supports VNet Integration
}

# 4. App Service (Linux Web App)
resource "azurerm_linux_web_app" "app" {
  name                = "app-enterprise-${random_integer.suffix.result}"
  location            = azurerm_resource_group.rg.location
  resource_group_name = azurerm_resource_group.rg.name
  service_plan_id     = azurerm_service_plan.asp.id

  # ---------------------------------------------------------
  # VNET INTEGRATION & IDENTITY
  # ---------------------------------------------------------
  
  # Links the App Service to Subnet B from Phase 1
  virtual_network_subnet_id = azurerm_subnet.snet_appservice.id

  # Creates a System-Assigned Managed Identity in Microsoft Entra ID
  identity {
    type = "SystemAssigned"
  }

  site_config {
    always_on = true
    
    # CRITICAL: Forces ALL outbound traffic into the VNet so it can resolve the Private Endpoints
    vnet_route_all_enabled = true
  }

  # ---------------------------------------------------------
  # APPLICATION SETTINGS
  # ---------------------------------------------------------
  app_settings = {
    # Standard Application Insights injection
    "APPINSIGHTS_INSTRUMENTATIONKEY"        = azurerm_application_insights.appinsights.instrumentation_key
    "APPLICATIONINSIGHTS_CONNECTION_STRING" = azurerm_application_insights.appinsights.connection_string
    
    # How your code will securely fetch the SQL password from Key Vault without hardcoding it
    # Because we use Managed Identity, the app will authenticate silently in the background
    "DB_PASSWORD" = "@Microsoft.KeyVault(VaultName=${azurerm_key_vault.kv.name};SecretName=sql-admin-password)"
  }
}

# 5. Role-Based Access Control (RBAC)
# Grants the new App Service Managed Identity permission to read secrets inside the Key Vault
resource "azurerm_role_assignment" "app_kv_access" {
  scope                = azurerm_key_vault.kv.id
  role_definition_name = "Key Vault Secrets User"
  
  # Extracts the unique Object ID of the App Service we just created
  principal_id         = azurerm_linux_web_app.app.identity[0].principal_id
}