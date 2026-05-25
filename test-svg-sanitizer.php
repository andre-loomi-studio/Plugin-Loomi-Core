<?php
define( 'ABSPATH', __DIR__ . '/' );
require_once __DIR__ . '/includes/modules/class-loomi-svg.php';

$tests = [
    'clean'         => '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="red"/></svg>',
    'script_tag'    => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle r="10"/></svg>',
    'onload'        => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><circle r="10"/></svg>',
    'js_href'       => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href="javascript:alert(1)"><circle r="10"/></a></svg>',
    'style_payload' => '<svg xmlns="http://www.w3.org/2000/svg"><style>*{background:url(javascript:alert(1))}</style><circle r="10"/></svg>',
    'xxe'           => '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&xxe;</svg>',
    'billion_laughs' => '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY lol "lol"><!ENTITY lol2 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">]><svg xmlns="http://www.w3.org/2000/svg">&lol2;</svg>',
    'malformed'     => '<svg xmlns broken xml><<<',
    'data_svg_use'  => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="data:image/svg+xml;base64,PHN2ZyBvbmxvYWQ9YWxlcnQoMSk+"/></svg>',
    'data_png_use'  => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="data:image/png;base64,iVBORw0KG"/></svg>',
    'foreignobject' => '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></body></foreignObject></svg>',
];

$dangerous_pattern = '/<script|<style|on[a-z]+=|javascript:|<!ENTITY|<!DOCTYPE|data:image\/svg|<foreignObject/i';

$pass = 0;
$fail = 0;
foreach ( $tests as $name => $svg ) {
    $result = Loomi_SVG::sanitize( $svg );

    if ( $result === null ) {
        // Rejection is correct for malformed/xxe/billion_laughs
        $expected_rejection = in_array( $name, [ 'malformed', 'xxe', 'billion_laughs' ], true );
        $verdict            = $expected_rejection ? 'PASS (rejected)' : 'FAIL (rejected — expected sanitized output)';
        echo "[$name] $verdict\n";
        $expected_rejection ? $pass++ : $fail++;
        continue;
    }

    $dangerous = preg_match( $dangerous_pattern, $result );
    if ( $dangerous ) {
        echo "[$name] FAIL (dangerous payload survived)\n";
        echo "   -> " . substr( preg_replace( '/\s+/', ' ', $result ), 0, 200 ) . "\n";
        $fail++;
    } else {
        echo "[$name] PASS\n";
        echo "   -> " . substr( preg_replace( '/\s+/', ' ', $result ), 0, 200 ) . "\n";
        $pass++;
    }
}

echo "\n=== TOTAL: $pass passed, $fail failed ===\n";
exit( $fail > 0 ? 1 : 0 );
