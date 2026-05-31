# Pelican Extension for Paymenter

Paymenter server extension for provisioning and managing Pelican Panel servers through the Pelican Application API.

## Features

- Creates Pelican users from Paymenter service owners.
- Creates, suspends, unsuspends, terminates, and upgrades Pelican servers.
- Supports Pelican eggs directly through `/api/application/eggs`.
- Supports automatic deployment with Pelican node tags and port ranges.
- Supports fixed allocation selection with a port array for eggs that need multiple known ports.
- Exposes Pelican user-editable egg variables on the Paymenter checkout form.

## Installation

1. Clone or download this repository on your Paymenter server:

   ```bash
   git clone https://github.com/Gvolexe/PelicanExtentionPaymenter.git
   ```

2. Copy the `Pelican` directory into your Paymenter install at:

   ```text
   extensions/Servers/Pelican
   ```

   Example:

   ```bash
   cp -r PelicanExtentionPaymenter/Pelican /var/www/paymenter/extensions/Servers/Pelican
   ```

3. From the Paymenter install directory, run:

   ```bash
   php artisan app:extension:install server Pelican
   ```

4. In Paymenter, enable the `Pelican` server extension.
5. In Pelican, create an Application API key.
6. Give the key enough permissions for this extension:
   - read: eggs, nodes, allocations
   - read/write: users, servers
7. In Paymenter, configure the extension with your Pelican URL and Application API key.

## Product Setup

Create or edit a Paymenter product and select the Pelican server extension.

Important fields:

- `Node`: choose a specific Pelican node. Leave empty to let Pelican auto deploy.
- `Deployment Tags`: optional Pelican node tags for automatic deployment.
- `Egg ID`: Pelican egg to install.
- `Docker Image Override`: optional image override. Leave empty to use the egg default image.
- `Port ranges`: passed to Pelican auto deployment when `Port Array` is not used.
- `Port Array`: manually selects allocations and assigns selected ports to egg variables.

## Port Array

Use `Port Array` when an egg needs known port values in specific environment variables.

Example:

```json
{
  "SERVER_PORT": 7777,
  "NONE": [7778, 7779],
  "QUERY_PORT": 27015,
  "RCON_PORT": 27020
}
```

Rules:

- `SERVER_PORT` is required and becomes the default Pelican allocation.
- Any key matching an egg environment variable updates that variable with the selected port.
- `NONE` reserves extra allocations without writing to an environment variable.
- If the exact requested port is unavailable, the extension selects the next higher free port on the chosen node. If none is higher, it uses the lowest available free port.

## Notes

- Pelican's current Application API uses node tags for deployment. The old Pterodactyl location flow is deprecated and not used here.
- If you choose a specific node, the extension picks an available allocation from that node and creates the server with explicit allocation IDs.
- The Paymenter product page fetches nodes and eggs from Pelican, so the Pelican API key must be valid before configuring products.
