<?php

class TCGRateCache
{
    private const CACHE_KEY_PREFIX = 'tcg_rate_cache_';
    private const DAY_IN_SECONDS   = 86400;

    private int $cache_duration;

    public function __construct($cache_duration = self::DAY_IN_SECONDS)
    {
        $this->cache_duration = $cache_duration;
    }

    public function get_cached_rate($package)
    {
        $cache_key = $this->generate_cache_key($package);

        return get_transient($cache_key);
    }

    public function set_cached_rate($package, $rate)
    {
        $cache_key = $this->generate_cache_key($package);
        set_transient($cache_key, $rate, $this->cache_duration);
    }

    public function delete_cached_rate($package)
    {
        $cache_key = $this->generate_cache_key($package);
        delete_transient($cache_key);
    }

    public function clear_tcg_cache()
    {
        global $wpdb;
        $cache_key_pattern = '%' . self::CACHE_KEY_PREFIX . '%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $cache_key_pattern
            )
        );
    }

    private function generate_cache_key($package)
    {
        $contents       = $package['contents'] ?? [];
        $destination    = $package['destination'] ?? [];
        $insurance      = $package['insurance'] ?? false;
        $optIns         = $package['ship_logic_optins'] ?? [];
        $timeBaseOptIns = $package['ship_logic_time_based_optins'] ?? [];
        unset($destination['pudo-select']);
        unset($destination['pudo-locker-origin']);
        unset($destination['pudo-locker-destination']);
        $hash = md5(json_encode([
                                    'contents'       => $contents,
                                    'destination'    => $destination,
                                    'insurance'      => $insurance,
                                    'optIns'         => $optIns,
                                    'timeBaseOptIns' => $timeBaseOptIns,
                                ]));

        return self::CACHE_KEY_PREFIX . $hash;
    }
}