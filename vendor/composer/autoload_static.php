<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitb6252c0150cf11c40f7ddf8c5b1d5147
{
    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'Location\\' => 9,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Location\\' => 
        array (
            0 => __DIR__ . '/..' . '/mjaschen/phpgeo/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitb6252c0150cf11c40f7ddf8c5b1d5147::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitb6252c0150cf11c40f7ddf8c5b1d5147::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitb6252c0150cf11c40f7ddf8c5b1d5147::$classMap;

        }, null, ClassLoader::class);
    }
}
