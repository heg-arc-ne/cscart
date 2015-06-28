DROP TABLE IF EXISTS `?:settings_vendor_values`;
CREATE TABLE IF NOT EXISTS `?:settings_vendor_values` (
   `object_id` mediumint(8) unsigned NOT NULL,
   `company_id` int(11) unsigned NOT NULL,
   `value` varchar(255) NOT NULL DEFAULT '',
   PRIMARY KEY (`object_id`,`company_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
UPDATE `?:shipping_services` SET status='A', carrier='USP', module='usps', code='Standard Post M', sp_file='' WHERE service_id='48';
UPDATE `?:shipping_services` SET status='A', carrier='USP', module='usps', code='Standard Post N', sp_file='' WHERE service_id='49';
UPDATE `?:shipping_services` SET status='A', carrier='USP', module='usps', code='First Class Package International Service', sp_file='' WHERE service_id='204';
UPDATE `?:language_values` SET value='Machinable (First-Class Mail or Standard Post)' WHERE name='ship_usps_machinable';
UPDATE `?:language_values` SET value='USPS Tracking/Delivery confirmation' WHERE name='usps_service_delivery_confirmation';
UPDATE `?:shipping_service_descriptions` SET description='USPS Standard Machinable' WHERE service_id='48';
UPDATE `?:shipping_service_descriptions` SET description='USPS Standard Non Machinable' WHERE service_id='49';
DELETE FROM `?:language_values` WHERE name='usps_service_restricted_delivery';
INSERT INTO `?:language_values` (lang_code, name, value) VALUES ('EN', 'paypal_security_error', 'Your order was not placed: the recipient PayPal account is wrong.') ON DUPLICATE KEY UPDATE `lang_code` = `lang_code`;
INSERT INTO `?:language_values` (lang_code, name, value) VALUES ('EN', 'usps_service_edelivery_confirmation', 'e-Delivery Confirmation') ON DUPLICATE KEY UPDATE `lang_code` = `lang_code`;
