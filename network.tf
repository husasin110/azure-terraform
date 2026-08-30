# 1. Resource Group
resource "azurerm_resource_group" "rg" {
  name     = "rg-enterprise-app-prod"
  location = "southindia" # Keeping your preferred region
}

# 2. Virtual Network
resource "azurerm_virtual_network" "vnet" {
  name                = "vnet-enterprise-app"
  location            = azurerm_resource_group.rg.location
  resource_group_name = azurerm_resource_group.rg.name
  address_space       = ["10.1.0.0/16"]
}

# 3. Subnet A: For Private Endpoints (Key Vault & SQL)
resource "azurerm_subnet" "snet_endpoints" {
  name                 = "snet-private-endpoints"
  resource_group_name  = azurerm_resource_group.rg.name
  virtual_network_name = azurerm_virtual_network.vnet.name
  address_prefixes     = ["10.1.1.0/24"]
}

# 4. Subnet B: For App Service VNet Integration
resource "azurerm_subnet" "snet_appservice" {
  name                 = "snet-app-integration"
  resource_group_name  = azurerm_resource_group.rg.name
  virtual_network_name = azurerm_virtual_network.vnet.name
  address_prefixes     = ["10.1.2.0/24"]

  # CRITICAL: Delegation tells Azure this subnet is exclusively for App Services
  delegation {
    name = "appservice-delegation"
    service_delegation {
      name    = "Microsoft.Web/serverFarms"
      actions = ["Microsoft.Network/virtualNetworks/subnets/action"]
    }
  }
}

# 5. Private DNS Zones
# Required so your App Service knows how to resolve the internal IP of the endpoints
resource "azurerm_private_dns_zone" "dns_keyvault" {
  name                = "privatelink.vaultcore.azure.net"
  resource_group_name = azurerm_resource_group.rg.name
}

resource "azurerm_private_dns_zone" "dns_sql" {
  name                = "privatelink.database.windows.net"
  resource_group_name = azurerm_resource_group.rg.name
}

# 6. Link the DNS Zones to the Virtual Network
resource "azurerm_private_dns_zone_virtual_network_link" "link_kv_dns" {
  name                  = "link-kv-vnet"
  resource_group_name   = azurerm_resource_group.rg.name
  private_dns_zone_name = azurerm_private_dns_zone.dns_keyvault.name
  virtual_network_id    = azurerm_virtual_network.vnet.id
}

resource "azurerm_private_dns_zone_virtual_network_link" "link_sql_dns" {
  name                  = "link-sql-vnet"
  resource_group_name   = azurerm_resource_group.rg.name
  private_dns_zone_name = azurerm_private_dns_zone.dns_sql.name
  virtual_network_id    = azurerm_virtual_network.vnet.id
}