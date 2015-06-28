DELETE FROM `?:payment_descriptions` WHERE payment_id IN (SELECT payment_id FROM ?:payments WHERE processor_id IN (SELECT processor_id FROM `?:payment_processors` WHERE processor_script = 'google_checkout.php'));
DELETE FROM `?:payments` WHERE processor_id IN (SELECT processor_id FROM `?:payment_processors` WHERE processor_script = 'google_checkout.php');
DELETE FROM `?:payment_processors` WHERE processor_script='google_checkout.php';
DELETE FROM `?:settings_objects` WHERE name='redirect_to_cart';
