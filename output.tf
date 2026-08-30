output "public_ip_address" {
  value       = azurerm_linux_virtual_machine.main.public_ip_address
  description = "The public IP address of the newly created Linux server."
}