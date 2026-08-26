variable "resource_group_name" {
  type        = string
  description = "The name of the resource group"
  default     = "my-linux-rg"
}

variable "location" {
  type        = string
  description = "The Azure region to deploy into"
  default     = "IndiaSouthCentral"
}

variable "admin_password" {
  type        = string
  description = "The password for the VM admin user"
  sensitive   = true # This hides the password from the terminal logs!
}