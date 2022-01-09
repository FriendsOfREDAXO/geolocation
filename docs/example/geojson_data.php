<?php
// Musterdaten für geoJSON aus der Leaflet-Dokumentation (in PHP-Array-Notation)
// https://leafletjs.com/examples/geojson/sample-geojson.js

$geojsonFreeBus = [
  'type' => 'FeatureCollection',
  'features' => [
    0 => [
      'type' => 'Feature',
      'geometry' => [
        'type' => 'LineString',
        'coordinates' => [
          0 => [
            0 => -105.00341892242432,
            1 => 39.75383843460583,
          ],
          1 => [
            0 => -105.0008225440979,
            1 => 39.751891803969535,
          ],
        ],
      ],
      'properties' => [
        'popupContent' => 'This is a free bus line that will take you across downtown.',
        'underConstruction' => false,
      ],
      'id' => 1,
    ],
    1 => [
      'type' => 'Feature',
      'geometry' => [
        'type' => 'LineString',
        'coordinates' => [
          0 => [
            0 => -105.0008225440979,
            1 => 39.751891803969535,
          ],
          1 => [
            0 => -104.99820470809937,
            1 => 39.74979664004068,
          ],
        ],
      ],
      'properties' => [
        'popupContent' => 'This is a free bus line that will take you across downtown.',
        'underConstruction' => true,
        'xxstyle' => [
            'color' => 'red',
        ]
      ],
      'id' => 2,
    ],
    2 => [
      'type' => 'Feature',
      'geometry' => [
        'type' => 'LineString',
        'coordinates' => [
          0 => [
            0 => -104.99820470809937,
            1 => 39.74979664004068,
          ],
          1 => [
            0 => -104.98689651489258,
            1 => 39.741052354709055,
          ],
        ],
      ],
      'properties' => [
        'popupContent' => 'This is a free bus line that will take you across downtown.',
        'underConstruction' => false,
      ],
      'id' => 3,
    ],
  ],
];

$geosonLightRailStop = [
  'type' => 'FeatureCollection',
  'features' => [
    0 => [
      'type' => 'Feature',
      'properties' => [
        'popupContent' => '18th & California Light Rail Stop',
      ],
      'geometry' => [
        'type' => 'Point',
        'coordinates' => [
          0 => -104.98999178409576,
          1 => 39.74683938093904,
        ],
      ],
    ],
    1 => [
      'type' => 'Feature',
      'properties' => [
        'popupContent' => '20th & Welton Light Rail Stop',
      ],
      'geometry' => [
        'type' => 'Point',
        'coordinates' => [
          0 => -104.98689115047453,
          1 => 39.747924136466565,
        ],
      ],
    ],
  ],
];

$geojsonBicycleRental = [
  'type' => 'FeatureCollection',
  'features' => [
    0 => [
      'geometry' => [
        'type' => 'Point',
        'coordinates' => [
          0 => -104.9998241,
          1 => 39.7471494,
        ],
      ],
      'type' => 'Feature',
      'properties' => [
        'popupContent' => 'This is a B-Cycle Station. Come pick up a bike and pay by the hour. What a deal!',
      ],
      'id' => 51,
    ],
    1 => [
      'geometry' => [
        'type' => 'Point',
        'coordinates' => [
          0 => -104.9983545,
          1 => 39.7502833,
        ],
      ],
      'type' => 'Feature',
      'properties' => [
        'popupContent' => 'This is a B-Cycle Station. Come pick up a bike and pay by the hour. What a deal!',
      ],
      'id' => 52,
    ],
    2 => [
      'geometry' => [
        'type' => 'Point',
        'coordinates' => [
          0 => -104.9963919,
          1 => 39.7444271,
        ],
      ],
      'type' => 'Feature',
      'properties' => [
        'popupContent' => 'This is a B-Cycle Station. Come pick up a bike and pay by the hour. What a deal!',
      ],
      'id' => 54,
    ],
    3 => [
      'geometry' => [
        'type' => 'Point',
        'coordinates' => [
          0 => -104.9960754,
          1 => 39.7498956,
        ],
      ],
      'type' => 'Feature',
      'properties' => [
        'popupContent' => 'This is a B-Cycle Station. Come pick up a bike and pay by the hour. What a deal!',
      ],
      'id' => 55,
    ],
    4 => [
      'geometry' => [
        'type' => 'Point',
        'coordinates' => [
          0 => -104.9933717,
          1 => 39.7477264,
        ],
      ],
      'type' => 'Feature',
      'properties' => [
        'popupContent' => 'This is a B-Cycle Station. Come pick up a bike and pay by the hour. What a deal!',
      ],
      'id' => 57,
    ],
    5 => [
      'geometry' => [
        'type' => 'Point',
        'coordinates' => [
          0 => -104.9913392,
          1 => 39.7432392,
        ],
      ],
      'type' => 'Feature',
      'properties' => [
        'popupContent' => 'This is a B-Cycle Station. Come pick up a bike and pay by the hour. What a deal!',
      ],
      'id' => 58,
    ],
    6 => [
      'geometry' => [
        'type' => 'Point',
        'coordinates' => [
          0 => -104.9788452,
          1 => 39.6933755,
        ],
      ],
      'type' => 'Feature',
      'properties' => [
        'popupContent' => 'This is a B-Cycle Station. Come pick up a bike and pay by the hour. What a deal!',
      ],
      'id' => 74,
    ],
  ],
];

$geojsonCoorsField = [
   "type" => "Feature",
   "properties" => [
         "popupContent" => "Coors Field"
      ],
   "geometry" => [
            "type" => "Point",
            "coordinates" => [
               -104.99404191971,
               39.756213909328
            ]
         ]
];

$geojsonCampus = [
    "type" => "Feature",
    "properties" => [
          "popupContent" => "This is the Auraria West Campus",
          "style" => [
             "weight" => 2,
             "color" => "#999",
             "opacity" => 1,
             "fillColor" => "#B0DE5C",
             "fillOpacity" => 0.8
          ]
       ],
    "geometry" => [
        "type" => "MultiPolygon",
        "coordinates" => [
            [
                [
                    [ -105.00432014465, 39.747321954899 ],
                    [ -105.00715255737, 39.746200068352 ],
                    [ -105.0092124939, 39.74468219277 ],
                    [ -105.0106716156, 39.743626259601 ],
                    [ -105.01195907593, 39.742900296161 ],
                    [ -105.0098991394, 39.740788359028 ],
                    [ -105.00758171082, 39.740590361603 ],
                    [ -105.00346183777, 39.740590361603 ],
                    [ -105.0009727478, 39.740590361603 ],
                    [ -105.00062942505, 39.740722359949 ],
                    [ -105.00020027161, 39.741910333689 ],
                    [ -105.00071525574, 39.742768301986 ],
                    [ -105.0009727478, 39.743692255898 ],
                    [ -105.0009727478, 39.744616197421 ],
                    [ -105.00123023987, 39.745342142784 ],
                    [ -105.00183105469, 39.746134074457 ],
                    [ -105.00432014465, 39.747321954899 ]
                ],
                [
                    [ -105.00361204147, 39.743543764141 ],
                    [ -105.00301122665, 39.742784801272 ],
                    [ -105.00221729279, 39.743164283751 ],
                    [ -105.00283956528, 39.743906743427 ],
                    [ -105.00361204147, 39.743543764141 ]
                ]
            ],[
                [
                    [ -105.00942707062, 39.739897366137 ],
                    [ -105.00942707062, 39.739105362786 ],
                    [ -105.00685214996, 39.739237363976 ],
                    [ -105.00384807587, 39.739105362786 ],
                    [ -105.001745224, 39.739039362096 ],
                    [ -105.00041484833, 39.739105362786 ],
                    [ -105.00041484833, 39.739798366216 ],
                    [ -105.00535011292, 39.739864366179 ],
                    [ -105.00942707062, 39.739897366137 ]
                ]
            ]
        ],
    ]
];
