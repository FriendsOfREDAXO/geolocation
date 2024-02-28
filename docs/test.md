Hier ein Beispiel:

```php
$id = \rex_request( FriendsOfRedaxo\Geolocation\KEY_MAPSET, 'integer', 1 );
$mapset = FriendsOfRedaxo\Geolocation\Mapset::take( $id );

dump(get_defined_vars());
```
