# Magento 2 Module for Webmecanik Automation integration

This Magento 2 module integrates your Magento store with Webmecanik Automation by publishing customer and newsletter subscriber updates to a message queue and consuming them to create/update contacts via the Webmecanik API.

Key capabilities:
- Publishes contact updates when customers register, update their profile/address, log in, or when newsletter subscriptions change.
- Provides a CLI command to queue a bulk export of all customers and guest newsletter opt-ins.
- Defines a consumer that processes queued contact updates, builds enriched contact payloads, and calls the remote API.
- Handles opt-out detection using Magento Newsletter subscriptions and manages export-stop behavior on repeated errors.
- Sends rejection notifications and stores rejection messages via plugins.
- Opt-out detection relies on Magento Newsletter subscription status for the consumer website scope.
- Webhook entry point for Webmecanik Automation callback on optin/optout changes

## Requirements
- Magento Open Source/Commerce 2.4.6
- PHP 8.1 or 8.2 (see composer.json)
- MySQL message queue (Magento built-in DB message queue is used)
- Webmecanik account with Custom Objects configured according to Magento categories IDs

## Installation
1. Webmecanik provide forked version of the mautic/api-library package. Please add custom repository in composer.json : https://github.com/Webmecanik/api-library
   - composer config repositories.webmecanik-api-library git https://github.com/Webmecanik/api-library --append
2. Install magento 2 webmecanik package via Composer :
   - composer require webmecanik/connector
3. Enable and upgrade:
   - bin/magento module:enable Webmecanik_Connector
   - bin/magento setup:upgrade
4. Flush caches:
   - bin/magento cache:flush

## Configuration
The module uses the following configuration paths (visible in core_config_data or admin if integrated):
- webmecanik_connector/general/enable_publisher (boolean): enable/disable all module operations.
- webmecanik_connector/general/enable_export (boolean): enable/disable contact export processing.
- webmecanik_connector/general/enable_retry_all : enable/disable auto-retry when API fails.
- webmecanik_connector/general/enable_debug : enable/disable debug logging / export API POST Paypload into file. Directory : var/log/webmecanik/
- webmecanik_connector/general/stop_export_on_error (boolean): if enabled, the module will automatically disable export after an error is encountered while processing a message.
- webmecanik_connector/general/client_id : client ID provided by Webmecanik.
- webmecanik_connector/general/client_secret : client secret provided by Webmecanik.
- webmecanik_connector/general/mautic_url : API URL provided by Webmecanik.
- webmecanik_connector/general/oauth2_authorize : CTA / Run authentication from Webmecanik Oauth2
- webmecanik_connector/general/webhook_secret_key : webhook secret key provided by Webmecanik. Needed for webhook verification.

## Events and Queue
- Observers (etc/events.xml):
  - customer_save_commit_after → Webmecanik\Connector\Observer\PublishCustomerOnSave
  - customer_address_save_commit_after → Webmecanik\Connector\Observer\PublishCustomerAddressOnSave
  - newsletter_subscriber_save_after → Webmecanik\Connector\Observer\PublishOnNewsletterSubscribe
- Frontend event (etc/frontend/events.xml):
  - customer_login → Webmecanik\Connector\Observer\PublishCustomerOnLogin
- Queue consumer (etc/queue_consumer.xml):
  - Consumer name: contact.updated
  - Queue: webmecanik_contact_updated
  - Handler: Webmecanik\Connector\Model\ConsumerContact::process

## CLI Commands
- Export all contacts (customers and guest newsletter opt-ins):
  - bin/magento webmecanik:export-all-contacts [--full-export]
  - By default, exports up to 1000 customers and 1000 guest opt-ins; use --full-export to remove the limit.
  - After queuing export messages, start the consumer to process them:
    - bin/magento queue:consumers:start contact.updated

## Consumer Processing
Class: Webmecanik\Connector\Model\ConsumerContact
- Builds a contact payload using customer and address data (if a customer exists for the email) and newsletter opt-out state.
- Writes payloads to a file via a writer service and calls an API client to create/update the contact.
- On exceptions, optionally disables export (based on configuration), and throws a JSON-formatted rejection containing both request and response to aid troubleshooting.

Notes on data handling:
- Country is mapped from the billing address; special names may be provided via a custom map or via Magento Directory.
- Mobile vs landline detection for French phone numbers is performed using a simple pattern; only one field is filled accordingly.

### Contact Payload: Custom Objects and Linked Items

When a Magento customer exists for the exported email, the consumer enriches the contact payload with purchase history using custom objects. Orders are sent as a custom object (`alias: "orders"`), and each order includes linked custom objects for its items (`alias: "items"`).

#### `customObjects` on the contact

The top-level contact payload contains a `customObjects` field:
{ "email": "john.doe@example.com", // ... "customObjects": { "data": } }

##### Orders (`alias: "orders"`)

- Location: `customObjects.data[]`
- Each entry in `data` represents one order.

> Order attribute keys are exported **without underscores** (e.g. `base_grand_total` → `basegrandtotal`, `ext_order_id` → `extorderid`).

##### Linked items (`linkedCustomObjects` with `alias: "items"`)

- Location: `customObjects.data[].data[].linkedCustomObjects`
- Purpose: attach the ordered products to each order.

#### Structure :
```json
{
    "email": "***@***.***",
    "doNotContact": [
        {
            "reason": 0,
            "comments": "Non abonné web",
            "channel": "email"
        }
    ],
    "overwriteWithBlank": true,
    "customer_id": "2",
    "prefix": null,
    "firstname": "***",
    "lastname": "***",
    "phone": null,
    "mobile": "***",
    "address1": "***",
    "address2": null,
    "zipcode": "***",
    "city": "***",
    "country": "France",
    "group_id": "1",
    "dob": null,
    "last_active": "2025-10-30 12:00:00",
    "magento_created_at": "2025-10-30 12:00:00",
    "customObjects": {
        "data": [
            {
                "alias": "orders",
                "data": [
                    {
                        "name": 5,
                        "attributes": {
                            "id": "5",
                            "extorderid": null,
                            "status": "processing",
                            "incrementid": "000000005",
                            "basediscountamount": 0,
                            "baseshippingamount": 5,
                            "baseshippingtaxamount": 0,
                            "basetaxamount": 0,
                            "basegrandtotal": 37,
                            "couponcode": null,
                            "shippingdescription": "Flat Rate - Fixed",
                            "paymentmethod": "checkmo",
                            "createdat": "2025-10-30 12:00:00",
                            "totalitemcount": 1
                        },
                        "linkedCustomObjects": [
                            {
                                "alias": "items",
                                "data": [
                                    {
                                        "name": "Strive Shoulder Pack",
                                        "attributes": {
                                            "sku": "24-MB04",
                                            "name": "Strive Shoulder Pack",
                                            "categories": [
                                                "4"
                                            ]
                                        }
                                    }
                                ]
                            }
                        ]
                    }
                ]
            }
        ]
    }
}
```

## Plugins and Admin UI
- Plugins are declared in etc/di.xml to:
  - save and remove rejection messages for MQ processing,
  - send notification email on rejection,
  - prevent message reading or kill consumers on poison pills (safety mechanisms).
- The module provides a UI data provider mapping for a queue grid and email templates for notifications.

## Troubleshooting
- Export disabled: If errors occur and stop_export_on_error is enabled, the module will set webmecanik_connector/general/enable_export to 0. Re-enable it in configuration to resume processing.
- Consumer not running: Ensure you start the consumer with `bin/magento queue:consumers:start contact.updated`.
- Permissions: Ensure the Magento process can write any payload/log files used by the module (depends on your environment).
- Logs: Check var/log for related entries. The module uses PSR logger and console output for CLI.

## Module Metadata
- Module: Webmecanik_Connector (etc/module.xml)
- Composer package: webmecanik/connector
- Requires: magento/framework 103.0.*, mautic/api-library

## License
See LICENSE files in the project root. This module follows the project’s licensing terms.
