=== The Courier Guy Shipping for WooCommerce ===
Tags: ecommerce, e-commerce, woocommerce, shipping, courier
Requires at least: 5.6.0
Tested up to: 6.5.4
Requires PHP: 8.0
Stable tag: 5.1.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This is the official WooCommerce extension to ship products using The Courier Guy.

== Description ==

The Courier Guy extension for WooCommerce enables you to ship products using The Courier Guy.

= Why choose The Courier Guy? =

The Courier Guy has built a strong reputation through strong customer relations and effective personal service. Today The Courier Guy is trusted, recognised and the fastest growing courier company in South Africa.

***DISCLAIMER***
Parcel sizes are based on your packaging structure. The plugin will compare the cart's total dimensions against "Flyer", "Medium" and "Large" parcel sizes to determine the best fit. The resulting calculation will be submitted to The Courier Guy as using the parcel's dimensions. By downloading and using this plugin, you accept that incorrect 'Parcel Size' settings may cause quotes to be inaccurate, and The Courier Guy will not be responsible for these inaccurate quotes.

== Installation ==

= MINIMUM REQUIREMENTS =
A Courier Guy account.
Please ensure that your Courier Guy account has credit, if there is no credit in your Courier Guy account, then the plugin will not function correctly.
Visit the [The Courier Guy Website](https://www.thecourierguy.co.za/contact/) page for more details.

= AUTOMATIC INSTALLATION =
Automatic installation is the easiest option — WordPress will handle the file transfer, and you won’t need to leave your web browser. To do an automatic install of 'The Courier Guy Shipping for WooCommerce', log in to your WordPress dashboard, navigate to the Plugins menu, and click “Add New.”

In the search field type “The Courier Guy Shipping for WooCommerce,” then click “Search Plugins.” Once you’ve found the plugin, you can view details or click “Install Now”. WordPress should take it from there.

= MANUAL INSTALLATION =
Manual installation method requires downloading the 'The Courier Guy Shipping for WooCommerce' plugin and uploading it to your webserver via your favourite FTP application. The WordPress codex contains instructions on how to do this here.

= UPDATING =
Automatic updates should work smoothly, but we still recommend you back up your site.

= CONFIGURATION =

To configure your shipping, log in to your WordPress dashboard, navigate to the Woocommerce -> Settings menu, click the Shipping tab, and click “Add shipping zone.”

Fill out the form as follows, please also see the attached screenshots:

**Zone name**
The Courier Guy

**Zone regions**
Select regions as desired

**Shipping methods**
Click the 'Add shipping method', select 'The Courier Guy' from the available options and click 'Add shipping method'.

Now you can edit the newly created Shipping Method.

Fill out the form as follows, please also see the attached screenshots:

**Title**
The Courier Guy

**Account number**
The account number supplied by The Courier Guy for integration purposes.

**Tax status**
VAT applies or not

**Access Key ID**
The access key ID for the Ship Logic API (legacy).

**Access Key**
The secret access key for the Ship Logic API (legacy).

**Access Token**
The access token for the Ship Logic API (V2).

**Company Name**
The name of your company.

**Shop Street Number and Name**
The address used to calculate shipping, this is considered the collection point for the parcels being shipping. e.g 12 My Road

**Shop Suburb**
Suburb forms part of the shipping address e.g Howick North

**Shop City**
City forms part of the shipping address e.g Howick

**Shop State or Province**
State / Province forms part of the shipping address e.g Kwazulu-Natal

**Shop Country**
Country two-letter code forms part of the shipping address e.g ZA

**Shop Postal Code**
The address used to calculate shipping, this is considered the collection point for the parcels being shipping.

**Shop Phone**
The telephone number to contact the shop, this may be used by the courier.

**Shop Contact Name**
The contact name of the shop, this may be used by the courier.

**Shop Email**
The email to contact the shop, this may be used by the courier.

**Exclude Rates**
Select the rates that you wish to always be excluded from the available rates on the checkout page.

**Percentage Markup**
Percentage markup to be applied to each quote.

**Parcels - Flyer Size: Length of Flyer**
Length of the Flyer - required

**Parcels - Flyer Size: Width of Flyer**
Width of the Flyer - required

**Parcels - Flyer Size: Height of Flyer**
Height of the Flyer - required

**Parcels - Medium Parcel Size: Length of Medium Parcel**
Length of the Medium Parcel - optional

**Parcels - Medium Parcel Size: Width of Medium Parcel**
Width of the Medium Parcel - optional

**Parcels - Medium Parcel Size: Height of Medium Parcel**
Height of the Medium Parcel - optional

**Parcels - Large Parcel Size: Length of Large Parcel**
Length of the Large Parcel - optional

**Parcels - Large Parcel Size: Width of Large Parcel**
Width of the Large Parcel - optional

**Parcels - Large Parcel Size: Height of Large Parcel**
Height of the Large Parcel - optional

**Enable free shipping**
This will enable free shipping over a specified amount

**Rates for free Shipping**
Select the rates that you wish to enable for free shipping

**Amount for free Shipping**
Enter the amount for free shipping when enabled

**Enable free shipping from product setting**
This will enable free shipping if the product is included in the basket

**Enable WooCommerce Logging**
Check this to enable Monolog logging for this plugin.
Remember to empty out logs when done.

**Enable Method Box on Checkout**
Check this to enable the Method Box on checkout page

**Use non-standard packing algorithm**
Check this to use the non-standard packing algorithm.<br> This is more accurate but will also use more server resources and may fail on shared servers.

**Frequently Asked Questions**

= Does this require a Courier Guy account? =

Yes! A Courier Guy account is required. To open an account, please refer to [The Courier Guy](https://www.thecourierguy.co.za/contact/)

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the Plugin Forum.

*Parcel Configuration and Accurate Quotes*
For accurate quotes to display, parcel dimensions for 'Flyer', 'Medium' and 'Large' parcels should be configured according to your needs. The algorithm will work out how many products will fit into each parcel, starting with the smallest size and working its way up. If you only have one or two parcel size (I.E. you don't use the flyer bag), dimensions of 1, 1, 1 can be used. All products will also need the correct dimensions and weights configured under their WooCommerce product settings.

*WCFM and WC Marketplace*
If this plugin is used together with the extension "WCFM and WC Marketplace - The Courier Guy Shipping for WooCommerce" it is possible to use vendor store addresses to override the base TCG shipping address
which is configured in the TCG shipping plugin.
To do this, from the Store Manager settings, select Store Vendors / Vendor / Details / Settings / Store Shipping.
Within the Store Shipping tab enable shipping and set processing time and shipping type. Then disable
shipping and update the settings. These steps are necessary for the vendor id to be passed to the shipping
module, and if it is not done, the default origin settings will be used.

*Product Settings*
There are three The Courier Guy specific settings in product settings
- Free Shipping - if enabled, a cart containing that product qualifies for free shipping
- Prohibit The Courier Guy - if enabled, TCG shipping will be disabled for that cart
 (both the above apply per vendor in the cart in a multi-vendor environment)
- Always pack as a single parcel - if enabled, each item of such a product will be a single parcel


*Services*
Services are divided into 3 areas, local, national and regional depending on the "collection" and
"delivery" address

For “Local” most developers allow LOF (Local Overnight Flyer) and LOX (Local Overnight Parcel)
over 40kg you will only get the ECO service for local
For “National” cheapest is ECO
For “Regional” cheapest is ECOR



Service             Service level   Service level     Volumetric   Weight   Weight   Weight
level               name            description       factor       from     to       unlimited
code

ECO                 Economy         Expect delivery   4000         25                TRUE
                                    between 3 - 4
                                    Days

ECOR                Economy         Expect delivery   4000         10       25       FALSE
                    Regional        between 3 - 5
                                    Days

ECOR                Economy         Expect delivery
                    Regional        between 3 - 5
                                    Days

OVNR                Overnight       Expect delivery   5000         0                 TRUE
                    Regional        between 2 - 3
                                    Days

ECO                 Economy         Expect delivery   4000         10       25       FALSE
                                    between 3 - 4
                                    Days

PUDO
                    PUD0                              5000         0                 TRUE
RIN
                    International                     5000         0                 TRUE
                    Road

ECO
                    Economy         Expect delivery   4000         0        5        FALSE
                                    between 3 - 4
                                    days.

ECO                 Economy         Expect delivery   4000         5        10       FALSE
                                    between 3 - 4
                                    days.

ECO                 Economy         Expect delivery   4000         0        15       FALSE
                                    between 3 - 4
                                    days.

ECO                 Economy         Expect delivery   4000         15       25       FALSE
                                    between 3 - 4
                                    days.

ECO                 Economy         Expect delivery   4000         0                 TRUE
                                    between 3 - 4
                                    days.

ECOB                Economy Bulk    Expect delivery   4000         100               TRUE
                    Kiosk           between 3 - 4
                                    days.

ECOR                Economy         Expect delivery   4000         0        5        FALSE
                    Regional        between 3 - 5
                                    days.

ECOR                Economy         Expect delivery   4000         5        10       FALSE
                    Regional        between 3 - 5
                                    days.

ECOR                Economy         Expect delivery   4000         0        15       FALSE
                    Regional        between 3 - 5
                                    days.

ECOR                Economy         Expect delivery   4000         25                TRUE
                    Regional        between 3 - 5
                                    days.

LLX                 Local Late      Collection        5000         0        40       FALSE
                    Sameday         sameday after
                    Express         15:00,  but
                                    before 17:00,

                                    delivery after 90
                                    minutes.

ECORB               Economy         Expect delivery    4000        100               TRUE
                    Regional Bulk   between 3 - 5
                    Kiosk           days.

ECOR                Economy         Expect delivery    4000        0                 TRUE
                    Regional        between 3 - 5
                                    days.

INN                 International   Contact your       5000        0        30       FALSE
                                    nearest hub for
                                    transit times.

LOF                 Local           Collection must    5000        0        15       FALSE
                    Overnight       be booked by
                    Flyer           14:00, and ready
                                    by 14:30, to be
                                    delivered during
                                    the next
                                    business day.

LOX                 Local           Collection must    5000        0        40       FALSE
                    Overnight       be booked by
                    Parcel          14:00, and ready
                                    by 14:30, to be
                                    delivered during
                                    the next
                                    business day.

LSE                 Local Same      Collection must    5000        0        40       FALSE
                    Day Economy     be booked by
                                    10:30, and ready
                                    by 11:00, to be
                                    delivered by
                                    17:00 the same
                                    day.

LSF                Local Same       Collection must    5000        0        15       FALSE
                   Day Flyer        be booked by
                                    10:30, and ready
                                    by 11:00, to be
                                    delivered by
                                    17:00 the same
                                    day.

LSX                Local Sameday    Collection         5000        0        40       FALSE
                   Express          sameday after
                                    8:00 but before
                                    15:00, delivery
                                    within 90
                                    minutes.
OVNR               Overnight        Expect delivery    5000        0                 TRUE
                   Regional         between 2 - 3
days.

OVN                Overnight        Expect delivery    5000        0                 TRUE
                                    between 1 - 2
                                    days.

ECO                Economy          Expect delivery    4000        5        15       FALSE
                                    between 3 - 4
                                    Days

ECOR               Economy          Expect delivery    4000        5        15       FALSE
                   Regional         between 3 - 5
                                    Days

LOX                Local            Collection must    5000        0                 TRUE
                   Overnight        be booked by
                   Parcel           14:00, and ready
                   Ecomms           by 14:30, to be
                                    delivered during
                                    the next
                                    business day.

ECO                Economy          Expect delivery    4000       40                 TRUE
                                    between 3 - 4
                                    days.

ECO                Economy          Expect delivery    5000       0                  TRUE
                   (5000)           between 3 - 4
                                    days.

ECOR               Economy          Expect delivery    5000       0                  TRUE
                   Regional         between 3 - 5
                   (5000)           days.

LSE                Local Same       Collection must    5000       0                  TRUE
                   Day Economy      be booked by
                   Ecomms           10:30, and ready
                                    by 11:00, to be
                                    delivered by
                                    17:00 the same
                                    day.

We often suggest that the clients do not exclude the following shipping methods
●  ALL ECO-related shipping methods (E.g ECOR ,ECOB)
●  LOX and LOF, OVN is optional


== Changelog ==
= 5.1.2 - July 22, 2024
* Guzzle HTTP upgrade.

= 5.1.1 - June 11, 2024
* Tested with WooCommerce 8.9.3 and WordPress 6.5.4.
* General fixes.
* New waybill parcel description changes.
* Fix bug with WooCommerce recalculate shipping button.

= 5.1.0 - November 14, 2023
* Tested with WooCommerce 8.2.2 and WordPress 6.4.
* Fix issues with free shipping after coupons have been applied.
* "Access Token" label changed to "API Key".
* Add IND rate support.
* Add support for HPOS.
* Amend liability insurance instructions.
* Add notice about blocks compatibility with method box and liability insurance.
* Add notice about dimensions being in CM and weight in KG.
* Fix undefined array key error.
* Add "Return TCG Shipment" feature.

= 5.0.9 - July 12, 2023
* Tested with WooCommerce 7.8.2 and WordPress 6.2.
* Add Currency Converter Support.
* Add Currency to Shipping Meta.
* Add Shop Contact Name.
* Add WooCommerce Blocks support.
* Add support for new API keys and deprecate legacy API keys (AWS v4 key).
* Bug fixes and improvements.

= 5.0.8 - June 02, 2022
* Tested with WooCommerce 6.5.1 and WordPress 6.0.
* Throw error on an empty cart.
* Improve prohibited product handling.
* Fix parcel description not sent to the portal.
* Fix Billing Phone not sent to the portal.
* Fix Company Name not sent to the portal.
* Fix Street Addresses sent to the portal.
* Fix inflated insurance added to shipping quote.
* Fix payments going through with no available shipping methods.
* Improve the compatibility of "Send to Courier Guy" and "Print WayBill".

= 5.0.7 - January 28, 2022
* Tested with Wordpress 5.9
* Bug fixes and improvements.
* Re-enable generic description feature.

= 5.0.6 - January 25, 2022
* Remove rate descriptions from checkout.
* Fix price override tax calculations.
* Fix LSF booking as 'ECO'.
* Add option to enable/disable insurance.

= 5.0.5 - January 17, 2022
* Bug fixes and improvements.
* Fix undefined offset error.
* Fix VAT and tax.
* Fix empty quote warning.
* Fix Province/State on older WooCommerce.
* Fix invalid type issue.

= 5.0.4 - January 7, 2022
* Bug fixes and improvements.
* Fix "Always pack as single parcel" logic.
* Add "Enable specific shipping options" feature.
* Remove "Disable Shipping Options" as this is now redundant.
* Remove "The Courier Guy PUDO: Fuel charge" from exclude rates.
* Add "Rates for free Shipping" feature.
* Update "Send Order to Courier Guy" and "Print Waybill" for recent ShipLogic API changes.

= 5.0.3 - January 1, 2022
* Add additional rates to rate override features.

= 5.0.2 - December 30, 2021
* Fix percentage markup.
* Add Disable Shipping Options feature.
* Add Exclude Rates feature.
* Add Price Rate Override Per Service feature.
* Label Override Per Service feature.
* Fix warnings on datatypes for some server configurations.

= 5.0.1 - December 28, 2021
* Fix shipping options auto-selecting.
* Normalise weight to KG if config set to other weight units.
* Fix Uncaught TypeError: class TCG_Plugin does not have a method "updateTCGServiceOnOrder"

= 5.0.0 - December 27, 2021
* Initial version using Ship Logic API. PLEASE CONTACT TCG DIRECTLY BEFORE UPDATING.

= 4.5.4 - November 18, 2021
* Fix free shipping error on multi-box.
* Improve logic to prevent area/suburb field from being skipped.
* Add server side filtering of rates when method box is enabled.
* Fix undefined operand issue on items without dimensions.
* Change apiToken storage to use transient.
* Reduce potential of hanging on checkout.
* Improve items per box algorithm.
* Tested on WooCommerce 5.9.0.

= 4.5.0 - August 30, 2021
* Fix free shipping for product when “free shipping over a certain amount" is disabled.
* Make entire label clickable for method box feature.
* Fix failed to open stream for .htaccess.setup.
* Tested on WooCommerce 5.6.0.

= 4.4.9 - August 5, 2021
* Fix undefined index 'enablenonstandardpackingbox' warning.
* Encrypt waybill number name on the filesystem.
* Improve non-standard algorithm for single-product parcels.

= 4.4.8 - July 27, 2021
* Revert to using local storage of waybills but include htaccess file.
* Fix LSF showing up if cart is non-flyer compatible.
* Tested with Wordpress 5.8 and WooCommerce 5.5.2
* Improve alternate method calculations.

= 4.4.7 - June 28, 2021
* Fix rate filter error on WCMP.
* Add 'Use non-standard packing algorithm' option for improved quotes.
* Remove local caching of Waybills for better POPI compliance.
* Add "specinstruction" so customer order notes appear on waybill.
* Improve code format and refactor.
* Test on WooCommerce 5.4.1

= 4.4.6 - June 15, 2021
* Fix incorrect weight calculation.
* Add "SPX : Special Trip" to rate options.

= 4.4.5 - June 11, 2021
* Fix 'actmass' per parcel when multiple items of the same product.

= 4.4.4 - June 11, 2021
* Fix Undefined variable: bestFitIndex.
* Fix Province/State fixed width of 1px when method box is enabled.
* Show "Deliver to a different address?" checkbox when method box is disabled.
* Fix JSON decode array/stdclass issue in TCG_Plugin.php.

= 4.4.3 - May 15, 2021
* Fix issue where destperemail was not sent to TCG.

= 4.4.2 - May 13, 2021
* Fix Undefined index: error.
* Tested with Wordpress 5.7.2 and WooCommerce 5.3.0

= 4.4.1 - May 11, 2021
* Refactor product pooling for more efficient resource handling.
* Remove flyer services if parcel size too large.
* Add support for failures caused by long URIs sent.

= 4.4.0 - Apr 23, 2021
* Add exclude cartage from free shipping feature.
* Add disclaimer feature.
* Always select free shipping if present.
* Add 3 more custom parcel sizes.
* Fix customer name missing from destpers field if Company empty.
* Tested with WooCommerce 5.
* Remove pre-populate javascript implementation.
* Improve quote caching reset and reliability.
* Fix the “Prohibit The Courier Guy” function for variable products.
* Add (cm) to parcel dimensions titles.
* Improve product box calculations by adding the 'pool products' capability.
* Fix logging when getting a quote.
* Fix Internet Explorer 11 checkout issue.

= 4.3.5 - Feb 10, 2021
* Fix Fatal error: Uncaught Error: Unsupported operand types in /wp-content/plugins/the-courier-guy/Core/ParcelPerfectApiPayload.php:728 on some servers.
* Fix destpercell field to use order billing phone.
* Add 'Send to Courier Guy' and 'Print Waybill' to action items.
* Remove "Percentage for free Shipping".

= 4.3.4 - Jan 25, 2021
* Tested with WooCommerce 4.9.1 and Wordpress 5.6.
* Improve area/suburb field handing.
* Fix extra line added to WayBill.
* Change Monolog to the WooCommerce Logger.
* Don't stop checkout if shipping internationally.
* Improve product parcel calculations.
* Change the default flyer sizes.
* Add pool products capability.

= 4.3.3 - Nov 16, 2020
* Add cache buster.

= 4.3.2 - Nov 16, 2020
* Fix 'CRITICAL syntax error, unexpected '' (T_ENCAPSED_AND_WHITESPACE)' issue.

= 4.3.1 - Nov 16, 2020
* Add 'Prohibit The Courier Guy' in product settings if cart contains this product.
* Add Shipping Selector feature.
* Fix consistent naming of session variable and general tidy-up.
* Pre-populate city area in billing.

= 4.3.0 - Oct 23, 2020
* PLEASE NOTE: If you use a multivendor platform, please test the integration thoroughly after updating.
* Fix SyntaxError: JSON Parse error: Unrecognized token ‘<.
* Fix undefined index warnings.
* Force cart reload in shipping field change.

= 4.2.9 - Oct 12, 2020
* Add option for single product per parcel at the product level.
* Add option to remove lowest cost quotes in multi vendor.
* Improve handling of failed POST requests to Parcel Perfect quotes.
* Fix TCG VAT total added when using free shipping feature.
* Fix free shipping bug in multi vendor plugin.
* Fix missing instance_id for some third party plugins.
* Remove use of cookies which caused failures on large orders and in multi vendor.
* Remove LOF Only Service.

= 4.2.8 - Sep 25, 2020
* PLEASE NOTE: Parcel sizes are based on your packaging structure. The plugin will compare the cart's total dimensions against "Flyer", "Medium" and "Large" parcel sizes to determine the best fit. The resulting calculation will be submitted to The Courier Guy as using the parcel's dimensions. By downloading and using this plugin, you accept that incorrect 'Parcel Size' settings may cause quotes to be inaccurate, and The Courier Guy will not be responsible for these inaccurate quotes.
* Amend shipping calculations to account for the entire order rather than individual line items.

= 4.2.7 - Sep 16, 2020
* Fix session_set error on some servers.
* Fix Invalid Product error for variable products.

= 4.2.6 - Sep 11, 2020
* Correct for index name changes in $package when using multivendor package.
* Simplify order shipping calculations and configuration.
* Remove product parcel settings.
* Remove global product quantity setting.
* Fix undefined index errors.
* Change payload calculation method for new sizes.
* Add customer phone on destpercell.

= 4.2.5 - Aug 25, 2020
* First try order meta to find shipping total, otherwise try the session, and if that fails, fallback to the cookie.
* Fix issue on guest checkout where the customer name was lost on waybill.

= 4.2.4 - Aug 12, 2020
* PLEASE NOTE: Print pending waybills before updating.
* Make insurance on checkout optional.
* Fix "An error of type E_ERROR".
* Remove old barcode and dompdf libraries.
* Show "Print Waybill" and "Send Order to Courier Guy" as appropriate.
* Add backup Cookie method if shipping missing from session.
* Fix "Undefined index: billing_insurance".
* Add option for generic product description on waybill.

= 4.2.3 - Aug 05, 2020
* Fix Call to undefined method ParcelPerfectApiPayload::factorise() if settings not configured.
* Improve ambiguous label "South Africa Only" -> "Ship internationally using other carriers".
* Waybill in email invalid if ‘collect from courier guy’ is not enabled.

= 4.2.2 - Jul 31, 2020
* Fix malformed number error.
* Use WC() session rather than $_SESSION to fix missing shipping information on orders.
* Fix null string issue.
* Add NFS service.
* Fix variable product calculations.
* Make shipping insurance on checkout optional.

= 4.2.1 - Jul 29, 2020
* Remove delivery date selection from checkout.

= 4.2.0 - Jul 25, 2020
* Add conditional free shipping feature.
* Add custom label and location for suburb area field.
* Fix shipment notifications.
* Fix parcel size, volume and weight calculations.
* Add parcel dimension configuration at both global and product levels.
* Add order id as WayBill reference.
* Add order notes for Parcel Perfect endpoint queries.
* Problem of variable products not calculating resolved with new methods.
* Adjust Waybill position and add clickable link in emails.
* Fix deprecated code warnings.
* Fix PHP missing index warnings.
* Fix collections submitted for the following day.
* Fix contact number is present where the name is supposed to go.
* Add option: If free shipping is active, remove all other shipping methods from checkout.
* Add option: Enable free shipping if selected products are in the cart.
* Add option: Enable free shipping if shipping total is a selected percentage of the total order value.
* Added VAT option for TCG shipping.

= 4.2.1 - November 28, 2020
* Change monolog to WC_Logger
