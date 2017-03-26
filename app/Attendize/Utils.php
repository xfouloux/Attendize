<?php

namespace App\Attendize;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use PhpSpec\Exception\Exception;

class Utils
{
    /**
     * @return bool
     */
    public static function isCurrentUserRegistered()
    {
        return Auth::check() && Auth::user()->is_registered;
    }

    /**
     * @return bool
     */
    public static function isCurrentUserConfirmed()
    {
        return Auth::check() && Auth::user()->is_confirmed;
    }

    /***
     * @return bool
     */
    public static function isDatabaseSetup()
    {
        try {
            if (Schema::hasTable('accounts')) {
                return true;
            }
        } catch (\Exception $e) {}

        return false;
    }

    /**
     * @return bool
     */
    public static function isAppSAAS()
    {
        return self::isAttendizeCloud() || self::isCurrentEnvironmentDev();
    }

    /***
     * @return bool
     */
    public static function isAttendizeCloud()
    {
        return isset($_ENV['ATTENDIZE_CLOUD']) && $_ENV['ATTENDIZE_CLOUD'] == 'true';
    }

    /**
     * @return bool
     */
    public static function isCurrentEnvironmentDev()
    {
        return isset($_ENV['ATTENDIZE_DEV']) && $_ENV['ATTENDIZE_DEV'] == 'true';
    }

    public static function isSiteDownForMaintenance()
    {
        return file_exists(storage_path() . '/framework/down');
    }

    /**
     * Check if a user has admin access to events etc.
     *
     * @todo - This is a temp fix until user roles etc. are implemented
     * @param $object
     * @return bool
     */
    public static function userOwns($object)
    {
        if (!Auth::check()) {
            return false;
        }

        try {

            if (Auth::user()->account_id === $object->account_id) {
                return true;
            }

        } catch (Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Determine max upload size
     *
     * @return float|int
     */
    public static function file_upload_max_size()
    {
        static $max_size = -1;

        if ($max_size < 0) {
            // Start with post_max_size.
            $max_size = self::parseFileSize(ini_get('post_max_size'));

            // If upload_max_size is less, then reduce. Except if upload_max_size is
            // zero, which indicates no limit.
            $upload_max = self::parseFileSize(ini_get('upload_max_filesize'));
            if ($upload_max > 0 && $upload_max < $max_size) {
                $max_size = $upload_max;
            }
        }

        return $max_size;
    }

    /***
     * @param string $fileSizeString
     * @return float
     */
    public static function parseFileSize($fileSizeString)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $fileSizeString); // Remove the non-unit characters from the size.
        $fileSizeString = preg_replace('/[^0-9\.]/', '', $fileSizeString); // Remove the non-numeric characters from the size.
        if ($unit) {
            // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
            return round($fileSizeString * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($fileSizeString);
        }
    }
}
