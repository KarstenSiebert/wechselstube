<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | निम्न भाषा पंक्तियाँ वेलिडेटर क्लास द्वारा उपयोग किए जाने वाले डिफ़ॉल्ट
    | त्रुटि संदेश हैं। कुछ नियमों के कई संस्करण हो सकते हैं, जैसे कि size नियम।
    | आप यहाँ प्रत्येक संदेश को अपनी आवश्यकता के अनुसार बदल सकते हैं।
    |
    */

    'accepted' => ':attribute फ़ील्ड स्वीकार किया जाना चाहिए।',
    'accepted_if' => ':attribute फ़ील्ड तब स्वीकार किया जाना चाहिए जब :other :value हो।',
    'active_url' => ':attribute फ़ील्ड एक मान्य URL होना चाहिए।',
    'after' => ':attribute फ़ील्ड :date के बाद की तारीख होनी चाहिए।',
    'after_or_equal' => ':attribute फ़ील्ड :date के बाद या उसके बराबर की तारीख होनी चाहिए।',
    'alpha' => ':attribute फ़ील्ड में केवल अक्षर होने चाहिए।',
    'alpha_dash' => ':attribute फ़ील्ड में केवल अक्षर, संख्या, डैश और अंडरस्कोर होने चाहिए।',
    'alpha_num' => ':attribute फ़ील्ड में केवल अक्षर और संख्या होने चाहिए।',
    'array' => ':attribute फ़ील्ड एक array होना चाहिए।',
    'before' => ':attribute फ़ील्ड :date से पहले की तारीख होनी चाहिए।',
    'before_or_equal' => ':attribute फ़ील्ड :date से पहले या उसके बराबर की तारीख होनी चाहिए।',
    'between' => [
        'array' => ':attribute फ़ील्ड में :min और :max आइटम होने चाहिए।',
        'file' => ':attribute फ़ील्ड :min और :max किलोबाइट के बीच होना चाहिए।',
        'numeric' => ':attribute फ़ील्ड :min और :max के बीच होना चाहिए।',
        'string' => ':attribute फ़ील्ड :min और :max वर्णों के बीच होना चाहिए।',
    ],
    'boolean' => ':attribute फ़ील्ड केवल true या false हो सकता है।',
    'confirmed' => ':attribute फ़ील्ड की पुष्टि मेल नहीं खा रही।',
    'date' => ':attribute फ़ील्ड एक मान्य तारीख होनी चाहिए।',
    'email' => ':attribute फ़ील्ड एक मान्य ईमेल पता होना चाहिए।',
    'exists' => 'चयनित :attribute अमान्य है।',
    'file' => ':attribute फ़ील्ड एक फ़ाइल होना चाहिए।',
    'filled' => ':attribute फ़ील्ड का मान होना चाहिए।',
    'image' => ':attribute फ़ील्ड एक इमेज होना चाहिए।',
    'in' => 'चयनित :attribute अमान्य है।',
    'integer' => ':attribute फ़ील्ड एक पूर्णांक होना चाहिए।',
    'max' => [
        'array' => ':attribute फ़ील्ड में :max से अधिक आइटम नहीं हो सकते।',
        'file' => ':attribute फ़ील्ड :max किलोबाइट से अधिक नहीं हो सकता।',
        'numeric' => ':attribute फ़ील्ड :max से अधिक नहीं हो सकता।',
        'string' => ':attribute फ़ील्ड :max वर्णों से अधिक नहीं हो सकता।',
    ],
    'min' => [
        'array' => ':attribute फ़ील्ड में कम से कम :min आइटम होने चाहिए।',
        'file' => ':attribute फ़ील्ड कम से कम :min किलोबाइट होना चाहिए।',
        'numeric' => ':attribute फ़ील्ड कम से कम :min होना चाहिए।',
        'string' => ':attribute फ़ील्ड कम से कम :min वर्णों का होना चाहिए।',
    ],
    'numeric' => ':attribute फ़ील्ड एक संख्या होनी चाहिए।',
    'required' => ':attribute फ़ील्ड आवश्यक है।',
    'string' => ':attribute फ़ील्ड एक स्ट्रिंग होनी चाहिए।',
    'unique' => ':attribute पहले से मौजूद है।',
    'url' => ':attribute फ़ील्ड एक मान्य URL होना चाहिए।',
    'uuid' => ':attribute फ़ील्ड एक मान्य UUID होना चाहिए।',

    // Custom application-specific messages
    'rate_must_be_greater' => 'रेट > 0.700 होना चाहिए।',
    'please_provide_a_valid_token_name' => 'कृपया एक मान्य टोकन नाम दें।',
    'this_token_already_exists_for_your_account' => 'यह टोकन आपके खाते के लिए पहले से मौजूद है।',
    'the_token_must_not_be_longer_than_32_characters' => 'टोकन 32 वर्णों से अधिक लंबा नहीं होना चाहिए।',
    'please_provide_a_valid_value_for_decimals' => 'कृपया दशमलव के लिए एक मान्य मान दें।',
    'one_of_0_1_2_3_4_5_6' => '0,1,2,3,4,5,6 में से एक।',

    'the_policy_ID_must_be_56hex_chars' => 'पॉलिसी ID 56 हेक्स वर्णों की होनी चाहिए।',
    'the_policy_ID_contains_invalid_characters' => 'पॉलिसी ID में अमान्य वर्ण हैं।',
    'the_policy_ID_does_not_exist' => 'पॉलिसी ID मौजूद नहीं है।',
    'the_address_must_start_with_addr1' => 'पता addr1 से शुरू होना चाहिए।',
    'the_address_contains_invalid_characters' => 'पते में अमान्य वर्ण हैं।',

    'not_implemented_yet' => 'अभी तक लागू नहीं किया गया है',
    'number_must_be_less_than' => 'संख्या इससे कम या बराबर होनी चाहिए ',
    'babel_fee_not_updated' => 'Babel शुल्क अपडेट नहीं हुआ।',
    'babel_fee_updated_successfully' => 'Babel शुल्क सफलतापूर्वक अपडेट किया गया।',
    'babel_fee_deleted_successfully' => 'Babel शुल्क सफलतापूर्वक हटाया गया।',
    'babel_fee_not_deleted' => 'Babel शुल्क हटाया नहीं गया।',
    'contact_updated_successfully' => 'संपर्क सफलतापूर्वक अपडेट किया गया।',
    'contact_not_updated' => 'संपर्क अपडेट नहीं हुआ।',
    'contact_deleted_successfully' => 'संपर्क सफलतापूर्वक हटाया गया।',
    'contact_not_deleted' => 'संपर्क हटाया नहीं गया।',
    'payment_request_not_updated' => 'भुगतान अनुरोध अपडेट नहीं हुआ।',
    'payment_request_updated_successfully' => 'भुगतान अनुरोध सफलतापूर्वक अपडेट किया गया।',
    'payment_request_not_deleted' => 'भुगतान अनुरोध हटाया नहीं गया।',
    'payment_request_deleted_successfully' => 'भुगतान अनुरोध सफलतापूर्वक हटाया गया।',
    'payment_request_not_created' => 'भुगतान अनुरोध बनाया नहीं गया।',
    'payment_request_created_successfully' => 'भुगतान अनुरोध सफलतापूर्वक बनाया गया।',
    'token_not_present' => 'वॉलेट में टोकन मौजूद नहीं है।',

    'inbound_cost_updated_successfully' => 'लागत सफलतापूर्वक अपडेट हुई।',
    'inbound_cost_not_updated' => 'लागत अपडेट नहीं हुई।',
    'token_successfully_created_as_inbound_cost' => 'टोकन सफलतापूर्वक लागत के रूप में बनाया गया।',
    'token_not_created_as_inbound_cost' => 'टोकन लागत के रूप में नहीं बनाया गया।',
    'inbound_cost_not_deleted' => 'लागत हटाई नहीं गई।',
    'inbound_cost_deleted_successfully' => 'लागत सफलतापूर्वक हटाई गई।',
    'this_location_already_exists_for_your_account' => 'यह स्थान आपके खाते के लिए पहले से मौजूद है।',

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    'attributes' => [],

];
