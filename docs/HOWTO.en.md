# MageBooster AI connector (Mcp): how-to

- **Package:** `magebooster/module-mcp`
- **Licence:** paid
- **Support:** info@magebooster.com

## What it does

The Mcp module lets Claude or ChatGPT work in your store through the gateway at **mcp.magebooster.com**. The gateway passes requests to your store and keeps no copy of store data.

The connector is **off until you switch it on**. While it is off, its address answers "Not found", like any unknown URL.

### Tools available in this version

All tools in this version only read data. They do not change anything in the store.

| Tool | What it returns |
|---|---|
| `store_info` | Basic store information |
| `products_search` | Products matching a search |
| `product_get` | One product's details |
| `categories_tree` | The category tree |
| `orders_list` | Orders for a period of up to 366 days. Personal data is masked. |
| `sales_summary` | Sales totals for a period |
| `abandoned_carts` | Abandoned carts from the last 7 or 30 days. Personal data is masked. |
| `maintenance_status` | The status of caches and indexers |

If the Reports module is installed, it adds report, question and alert tools to this list. See the Reports guide.

### Planned tools

Tools that change products, prices, stock or settings are not part of this version. The `products_update` entry is only a placeholder: it is hidden from the tool list and cannot be called.

### Access levels

You choose the highest access level a connection can have:

- **Read only:** questions and reports. Nothing in the store changes.
- **Editor:** also products, prices, stock and categories. In this version, the only tools that use this level are the alert tools from Reports.
- **Full:** also theme, checkout and store settings. No tool in this version needs this level.

A connection never gets more than the highest level you set, even if it asks for more.

### Security

- **Signed requests.** Every request from the gateway is signed with HMAC-SHA256 using your connection key.
  - The request must carry a timestamp. It is accepted only within 5 minutes of the store's time.
  - Each request has a single-use nonce, kept for 10 minutes.
  - A request with a bad signature, an old timestamp or a reused nonce is refused.
- **Audit log.** Every call is written to the `mbmcp_audit` table with these details:
  - client, tool and role
  - arguments, with personal data removed
  - result (ok, denied or error)
  - number of items and duration
- **Merchant approval.** A new connection must be approved by an admin user in the Magento admin.
- **Cutting access.** Changing the connection key cuts every connection at once.

## Requirements

- PHP 8.0 or later
- Magento 2.4.4 to 2.4.9
- A MageBooster AI connector account at mcp.magebooster.com, which gives you the connection key
- **Luma edition:** `magebooster/module-mcp`
- **Hyvä edition:** `magebooster/module-mcp-hyva`

The two editions are sold separately. The module runs on the server and in the admin; it adds nothing to the storefront.

## Install with Composer (paid)

1. Add the repository:

   ```bash
   composer config repositories.magebooster composer https://repo.magebooster.com
   ```

2. Add your access details to `auth.json` in the Magento root. Use the username from your order email, and your serial number as the password:

   ```json
   {
     "http-basic": {
       "repo.magebooster.com": {
         "username": "<username from your order email>",
         "password": "<your serial number>"
       }
     }
   }
   ```

3. Install the package:

   ```bash
   composer require magebooster/module-mcp
   ```

   On a Hyvä store, install `magebooster/module-mcp-hyva` instead.

Keep `auth.json` out of version control.

**About the serial number:** the serial is used to download the package and is checked inside the store. See Licence check below.

## Install from a zip file

1. Unzip the archive into `app/code/MageBooster/Mcp`.
2. Run the commands below.

## Enable the module

```bash
bin/magento module:enable MageBooster_Mcp
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

## Admin configuration

Go to **Stores > Configuration > MageBooster > AI connector (Claude, ChatGPT)**.

| Field | Default | What it means |
|---|---|---|
| Enabled | No | Turns the connector on. When it is off, the connector address answers "Not found". |
| Highest access | Read only | The most a connection can do. Options: Read only, Editor, Full. |
| Connection key | (empty) | The key from mcp.magebooster.com. It is stored encrypted. Change it to cut every connection. |

### Permissions (ACL)

- `MageBooster_Mcp::config` allows an admin user to change these settings.
- `MageBooster_Mcp::connect` allows an admin user to approve new connections.

## Connecting Claude or ChatGPT

1. Get your connection key from mcp.magebooster.com.
2. Paste the key into **Connection key**, set **Enabled** to Yes, choose **Highest access**, and click **Save Config**.
3. In Claude or ChatGPT, add the MageBooster connector and start the sign-in.
4. The sign-in sends you to your store (`/mbmcp/connect/approve`) and then to the admin page **"Claude or ChatGPT wants to work in <store>"**. You may need to log in to the admin first.
5. Choose the access level for this connection. It cannot be higher than **Highest access**.
6. Click **Approve**, or click **Cancel** to refuse.
7. You return to Claude or ChatGPT, and the connection is ready.

A connection link is valid for 10 minutes. If it expires, start again from Claude or ChatGPT.

## For developers

### Endpoints

- `POST /mbmcp/agent/call` (frontend route `mbmcp`) receives calls from the gateway.
  - The body is JSON with `method` (`tools/list` or `tools/call`), `tool`, `args`, `role`, `client` and `request_id`.
  - The headers are `X-MB-Ts`, `X-MB-Nonce` and `X-MB-Sig`.
  - The signature is `hex(hmac_sha256(key, ts + "\n" + nonce + "\n" + rawBody))`.
- `GET /mbmcp/connect/approve` and the admin route `mbmcp/connect/approve` handle the sign-in.

### Errors

Errors are returned as JSON in the form `{ok:false, error:{code, message}}`. The codes are:

- `not_configured`
- `bad_request`
- `too_large`
- `unauthorized`
- `stale_request`
- `nonce_reused`
- `bad_signature`
- `unknown_tool`
- `role_denied`
- `internal`

### Adding tools

To add your own tools, register them in the module's tool pool through `di.xml`. Each tool states the lowest role it needs (read, editor or full). The Reports module is a working example.

## Testing

1. Leave **Enabled** off and open `https://<your-store>/mbmcp/agent/call`. You should get "Not found".
2. Switch the connector on and connect from Claude or ChatGPT as described above.
3. Ask a question such as "How many orders did we get last week?".
4. Check the `mbmcp_audit` table for the call.
5. Change the connection key and save. The next request from Claude or ChatGPT should be refused.

## Troubleshooting

- **"This connection link is not valid. Start again from Claude or ChatGPT."** The link was changed, or the connection key in the store does not match the one at mcp.magebooster.com.
- **`not_configured`:** the connector is on, but the connection key is empty.
- **`stale_request`:** the server clock is wrong. Sync it with NTP.
- **`role_denied`:** the tool needs a higher level than this connection has. Raise **Highest access** and connect again, if you want to allow it.
- **Requests are blocked before they reach Magento:** make sure your firewall or WAF lets `POST /mbmcp/agent/call` through with JSON bodies and the `X-MB-*` headers.

## Uninstall

1. Set **Enabled** to No.
2. Remove the connector in Claude or ChatGPT.
3. Run:

   ```bash
   bin/magento module:disable MageBooster_Mcp
   composer remove magebooster/module-mcp
   bin/magento setup:upgrade
   bin/magento cache:flush
   ```

The Reports module depends on this module, so remove Reports first.

The `mbmcp_audit` table stays in the database. Drop it if you no longer need the log.

## Licence check (serial number)

- Enter the serial in **Stores > Configuration > MageBooster > Licenses**, field **MageBooster AI connector (MCP)** (Hyvä edition: **MageBooster AI connector (MCP) (Hyvä edition)**). The serial starts with `MB1`.
- The **License status** table on that page shows each module as **Active**, **Grace period** or **Switched off**. `bin/magento magebooster:license:status` shows the same.
- Without a valid serial (none entered, not valid, expired, another domain or another product) the module keeps working for **30 days**.
  - The 30 days start the first time the store sees the module without a valid serial.
  - A valid serial ends the grace period. If that serial later stops being valid (for example it expires), a new 30-day period starts.
- During and after the grace period, every admin page shows a notice that cannot be closed. It names the module, the reason, and the days left or the date it was switched off, with links to the Licenses page and to your serial numbers at https://store.magebooster.com/licences/account/.
- Shoppers never see a licence message, and the check runs inside your store: nothing is sent to MageBooster.
- Development hosts (`localhost`, `127.0.0.1`, `*.localhost`, `*.test`, `*.local`) always pass the domain check.
- **After the 30 days:** the store's AI connector endpoint answers every request with `license_required` (HTTP 403), so Claude or ChatGPT get a clear "license required" reply, and new connections cannot be approved in the admin. Settings and the audit log are kept.

## Licence & copying

Commercial licence, see `LICENSE.txt`. Copyright (c) 2026 WebWorks 101 OÜ (MageBooster). All rights reserved.

- One serial number = one production installation (one store domain). Staging and development copies of that same store are allowed.
- Each serial number and each download is tied to the customer who bought it. Luma and Hyvä editions are separate products with separate serial numbers.
- You may modify the code for your own licensed store. Keep the copyright and licence notices.
- Not allowed: copying to other stores, redistributing, reselling, sublicensing, publishing in public repositories, marketplaces or forums, removing licence checks or these notices, sharing serial numbers.
- In case of unauthorised copying or use, the serial number is revoked without refund, updates and support stop, and WebWorks 101 OÜ may claim damages and take legal action. Estonian law applies; the court is Harju County Court.

## Support

info@magebooster.com
