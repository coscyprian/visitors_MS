<?php
// Taarifa za AfricasTalking
// Tumia environment variables badala ya hardcoded values kwa usalama
$at_username = getenv('AFRICASTALKING_USERNAME') ?: "sandbox"; // Tumia 'sandbox' kwa majaribio au username yako ya kweli
$at_api_key  = getenv('AFRICASTALKING_API_KEY') ?: ""; // Chukua hii kwenye dashboard ya AfricasTalking na set kama environment variable

// Hii ni link ya API yao
$at_url = "https://api.africastalking.com/version1/messaging"; 

// Kama unatumia Sandbox, URL inabadilika kidogo kuwa:
// $at_url = "https://api.sandbox.africastalking.com/version1/messaging";
?>