<?php
/**
 * See LICENSE.md for license details.
 */

namespace GlsGroup\Shipping\Api;

interface RelayPointRepositoryInterface
{

    /**
     *
     *
     * @param string $q q.
     * @param string $countryId countryId.
     * @return RelayPoint[] relaypoint list.
     * @api
     */
    public function getList($countryId, $q);
}
