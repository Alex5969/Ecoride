<?php

define('MONGO_DB_HOST', 'mongodb://localhost:27017');
define('MONGO_DB_NAME', 'ecoride_analytics');

class MongoDBLogger {
    private static $client = null;
    private static $database = null;

    private static function get_client() {
        if (self::$client === null) {
            try {
                self::$client = new MongoDB\Client(MONGO_DB_HOST);
            } catch (MongoDB\Driver\Exception\Exception $e) {
                error_log("MongoDB connection failed: " . $e->getMessage());
                return null;
            }
        }
        return self::$client;
    }

    private static function get_database() {
        if (self::$database === null) {
            $client = self::get_client();
            if ($client) {
                self::$database = $client->selectDatabase(MONGO_DB_NAME);
            }
        }
        return self::$database;
    }

    public static function log_event(string $collectionName, array $eventData): bool {
        $database = self::get_database();
        if (!$database) {
            return false;
        }
        try {
            $collection = $database->selectCollection($collectionName);
            $eventData['timestamp'] = date('Y-m-d H:i:s');
            $collection->insertOne($eventData);
            return true;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log("MongoDB log failed for collection " . $collectionName . ": " . $e->getMessage());
            return false;
        }
    }

    public static function aggregate_data(string $collectionName, array $pipeline): array {
        $database = self::get_database();
        if (!$database) {
            return [];
        }
        try {
            $collection = $database->selectCollection($collectionName);
            $result = $collection->aggregate($pipeline)->toArray();
            return $result;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log("MongoDB aggregation failed for collection " . $collectionName . ": " . $e->getMessage());
            return [];
        }
    }
}
