# 1. Fetch current Azure Tenant details
data "azurerm_client_config" "current" {}

# 2. Generate a random suffix for globally unique names (KV and SQL require this)
resource "random_integer" "suffix" {
  min = 10000
  max = 99999
}

# 3. Generate a secure, random password for the SQL Administrator
resource "random_password" "sql_admin_password" {
  length           = 16
  special          = true
  override_special = "!#$%&*()-_=+[]{}<>:?"
}

# ---------------------------------------------------------
# AZURE KEY VAULT & PRIVATE ENDPOINT
# ---------------------------------------------------------

resource "azurerm_key_vault" "kv" {
  name                        = "kv-entapp-${random_integer.suffix.result}"
  location                    = azurerm_resource_group.rg.location
  resource_group_name         = azurerm_resource_group.rg.name
  tenant_id                   = data.azurerm_client_config.current.tenant_id
  sku_name                    = "standard"
  
  # Enterprise Security: Disable public internet access
  public_network_access_enabled = false

  # We use RBAC instead of older Access Policies for modern identity management
  enable_rbac_authorization     = true 
}

resource "azurerm_private_endpoint" "pe_kv" {
  name                = "pe-keyvault"
  location            = azurerm_resource_group.rg.location
  resource_group_name = azurerm_resource_group.rg.name
  subnet_id           = azurerm_subnet.snet_endpoints.id

  private_service_connection {
    name                           = "psc-keyvault"
    private_connection_resource_id = azurerm_key_vault.kv.id
    is_manual_connection           = false
    subresource_names              = ["vault"] # Connects specifically to the Key Vault data plane
  }

  # Automatically registers the Private Endpoint IP into the DNS Zone from Phase 1
  private_dns_zone_group {
    name                 = "dns-group-kv"
    private_dns_zone_ids = [azurerm_private_dns_zone.dns_keyvault.id]
  }
}

# ---------------------------------------------------------
# AZURE SQL SERVER & PRIVATE ENDPOINT
# ---------------------------------------------------------

resource "azurerm_mssql_server" "sql_server" {
  name                         = "sql-entapp-${random_integer.suffix.result}"
  resource_group_name          = azurerm_resource_group.rg.name
  location                     = azurerm_resource_group.rg.location
  version                      = "12.0"
  administrator_login          = "sqladmin"
  administrator_login_password = random_password.sql_admin_password.result

  # Enterprise Security: Disable public internet access
  public_network_access_enabled = false
}

resource "azurerm_mssql_database" "sql_db" {
  name      = "sqldb-enterprise-app"
  server_id = azurerm_mssql_server.sql_server.id
  sku_name  = "S0"
}

resource "azurerm_private_endpoint" "pe_sql" {
  name                = "pe-sqlserver"
  location            = azurerm_resource_group.rg.location
  resource_group_name = azurerm_resource_group.rg.name
  subnet_id           = azurerm_subnet.snet_endpoints.id

  private_service_connection {
    name                           = "psc-sql"
    private_connection_resource_id = azurerm_mssql_server.sql_server.id
    is_manual_connection           = false
    subresource_names              = ["sqlServer"] # Connects specifically to the SQL data plane
  }

  private_dns_zone_group {
    name                 = "dns-group-sql"
    private_dns_zone_ids = [azurerm_private_dns_zone.dns_sql.id]
  }
}