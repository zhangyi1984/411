<?php

namespace FOO;

/**
 * Class Sync Job
 * Represents a scheduled job to update alerts in the ES index.
 * @package FOO
 */
class Sync_Job extends Job {
    public static $TYPE = 'sync';

    const CHUNK_SIZE = 1000;

    /**
     * Process any Alerts that are stale.
     * @return array null, array of errors and whether failures are ignorable.
     */
    public function run() {
        $client = new ESClient(false);

        $id = 0;
        $curr = 0;
        $target = $this->obj['target_id'];
        $query = [];
        if($target > 0) {
            $query['search_id'] = $target;
            $client->initializeIndex();
        } else {
            $client->destroyIndex();
            $client->initializeIndex();
        }
        $count = AlertFinder::countByQuery($query);

        do {
            $query['alert_id'] = [ModelFinder::C_GT => $id];
            $alerts = AlertFinder::getByQuery($query, self::CHUNK_SIZE, 0, [['alert_id', ModelFinder::O_ASC]]);

            foreach($alerts as $alert) {
                $id = $alert['alert_id'];
                $client->update($alert);
            }

            $curr += count($alerts);
            $this->setCompletion($count > 0 ? (($curr * 100) / $count):100);
        } while(count($alerts) > 0 && $curr < $count);

        $client->finalize();

        return [null, [], false];
    }
}
