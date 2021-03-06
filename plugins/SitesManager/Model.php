<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\SitesManager;

use Piwik\Access;
use Piwik\Date;
use Piwik\Db;
use Piwik\Common;
use Exception;

class Model implements \Piwik\Db\FactoryCreated
{
    protected static $rawPrefix = 'site';
    protected $table;
    protected $db;

    public function __construct()
    {
        $this->table = Common::prefixTable(self::$rawPrefix);
        $this->db = Db::get();
    }

    public function createSite($site)
    {
        $this->db->insert($this->table, $site);

        $idSite = $this->db->lastInsertId();

        return $idSite;
    }

    /**
     * Returns all websites belonging to the specified group
     * @param string $group Group name
     * @return array of sites
     */
    public function getSitesFromGroup($group)
    {
        $sites = $this->db->fetchAll("SELECT * FROM " . $this->table . "
                                           WHERE `group` = ?", $group);

        return $sites;
    }

    /**
     * Returns the list of website groups, including the empty group
     * if no group were specified for some websites
     *
     * @return array of group names strings
     */
    public function getSitesGroups()
    {
        $group = $this->db->quoteIdentifier('group');
        $groups = $this->db->fetchAll("SELECT DISTINCT $group FROM " . $this->table);

        $cleanedGroups = array();
        foreach ($groups as $group) {
            $cleanedGroups[] = $group['group'];
        }

        return $cleanedGroups;
    }

    /**
     * Returns all websites
     *
     * @return array The list of websites, indexed by idsite
     */
    public function getAllSites()
    {
        $sites = $this->db->fetchAll("SELECT * FROM " . $this->table . " ORDER BY idsite ASC");

        return $sites;
    }

    /**
     * Returns the list of the website IDs that received some visits since the specified timestamp.
     *
     * @param string $time
     * @param string $now
     * @return array The list of website IDs
     */
    public function getSitesWithVisits($time, $now)
    {
        $sites = $this->db->fetchAll("
            SELECT idsite FROM " . $this->table . " s
            WHERE EXISTS (
                SELECT 1
                FROM " . Common::prefixTable('log_visit') . " v
                WHERE v.idsite = s.idsite
                AND visit_last_action_time > ?
                AND visit_last_action_time <= ?
                LIMIT 1)
        ", array($time, $now));

        return $sites;
    }


    /**
     * Returns the list of websites ID associated with a URL.
     *
     * @param array $urls
     * @return array list of websites ID
     */
    public function getAllSitesIdFromSiteUrl(array $urls)
    {
        $siteUrlTable = Common::prefixTable('site_url');

        $ids = $this->db->fetchAll(
            'SELECT idsite FROM ' . $this->table . '
                    WHERE main_url IN ( ' . Common::getSqlStringFieldsArray($urls) . ') ' .
            'UNION
                SELECT idsite FROM ' . $siteUrlTable . '
                    WHERE url IN ( ' . Common::getSqlStringFieldsArray($urls) . ') ',

            // Bind
            array_merge( $urls, $urls)
        );

        return $ids;
    }

    /**
     * Returns the list of websites ID associated with a URL.
     *
     * @param string $login
     * @param array $urls
     * @return array list of websites ID
     */
    public function getSitesIdFromSiteUrlHavingAccess($login, $urls)
    {
        $siteUrlTable  = Common::prefixTable('site_url');
        $sqlAccessSite = Access::getSqlAccessSite('idsite');

        $ids = $this->db->fetchAll(
            'SELECT idsite
                FROM ' . $this->table . '
                    WHERE main_url IN ( ' . Common::getSqlStringFieldsArray($urls) . ')' .
            'AND idsite IN (' . $sqlAccessSite . ') ' .
            'UNION
                SELECT idsite
                FROM ' . $siteUrlTable . '
                    WHERE url IN ( ' . Common::getSqlStringFieldsArray($urls) . ')' .
            'AND idsite IN (' . $sqlAccessSite . ')',

            // Bind
            array_merge(    $urls,
                            array( $login ),
                            $urls,
                            array( $login )
            )
        );

        return $ids;
    }

    /**
     * Returns all websites with a timezone matching one the specified timezones
     *
     * @param array $timezones
     * @return array
     * @ignore
     */
    public function getSitesFromTimezones($timezones)
    {
        $query = 'SELECT idsite FROM ' . $this->table . '
                  WHERE timezone IN (' . Common::getSqlStringFieldsArray($timezones) . ')
                  ORDER BY idsite ASC';
        $sites = $this->db->fetchAll($query, $timezones);

        return $sites;
    }

    public function deleteSite($idSite)
    {
        $this->db->query("DELETE FROM " . $this->table . " WHERE idsite = ?", $idSite);
        $this->db->query("DELETE FROM " . Common::prefixTable("site_url") . " WHERE idsite = ?", $idSite);
        $this->db->query("DELETE FROM " . Common::prefixTable("access") . " WHERE idsite = ?", $idSite);
    }

    /**
     * Returns the list of websites from the ID array in parameters.
     *
     * @param array $idSites list of website ID
     * @param bool $limit
     * @return array
     */
    public function getSitesFromIds($idSites, $limit = false)
    {
        if (count($idSites) === 0) {
            return array();
        }

        if ($limit) {
            $limit = "LIMIT " . (int)$limit;
        } else {
            $limit = '';
        }

        $idSites = array_map('intval', $idSites);

        $sites = $this->db->fetchAll("SELECT * FROM " . $this->table . "
                                WHERE idsite IN (" . implode(", ", $idSites) . ")
                                ORDER BY idsite ASC $limit");

        return $sites;
    }

    /**
     * Returns the website information : name, main_url
     *
     * @throws Exception if the site ID doesn't exist or the user doesn't have access to it
     * @param int $idSite
     * @return array
     */
    public function getSiteFromId($idSite)
    {
        $site = $this->db->fetchRow("SELECT * FROM " . $this->table . "
                                          WHERE idsite = ?", $idSite);

        return $site;
    }

    /**
     * Returns the list of all the website IDs registered.
     * Caller must check access.
     *
     * @return array The list of website IDs
     */
    public function getSitesId()
    {
        $result  = $this->db->fetchAll("SELECT idsite FROM " . Common::prefixTable('site'));

        $idSites = array();
        foreach ($result as $idSite) {
            $idSites[] = $idSite['idsite'];
        }

        return $idSites;
    }

    /**
     * Returns the list of all URLs registered for the given idSite (main_url + alias URLs).
     *
     * @throws Exception if the website ID doesn't exist or the user doesn't have access to it
     * @param int $idSite
     * @return array list of URLs
     */
    public function getSiteUrlsFromId($idSite)
    {
        $urls = $this->getAliasSiteUrlsFromId($idSite);
        $site = $this->getSiteFromId($idSite);

        if (empty($site)) {
            return $urls;
        }

        return array_merge(array($site['main_url']), $urls);
    }

    /**
     * Returns the list of alias URLs registered for the given idSite.
     * The website ID must be valid when calling this method!
     *
     * @param int $idSite
     * @return array list of alias URLs
     */
    public function getAliasSiteUrlsFromId($idSite)
    {
        $result = $this->db->fetchAll("SELECT url FROM " . Common::prefixTable("site_url") . "
                                WHERE idsite = ?", $idSite);
        $urls = array();
        foreach ($result as $url) {
            $urls[] = $url['url'];
        }

        return $urls;
    }

    public function updateSite($site, $idSite)
    {
        $idSite = (int) $idSite;

        $this->db->update($this->table, $site, "idsite = $idSite");
    }

    /**
     * Returns the list of unique timezones from all configured sites.
     *
     * @return array ( string )
     */
    public function getUniqueSiteTimezones()
    {
        $results = $this->db->fetchAll("SELECT distinct timezone FROM " . $this->table);

        $timezones = array();
        foreach ($results as $result) {
            $timezones[] = $result['timezone'];
        }

        return $timezones;
    }

    /**
     * Updates the field ts_created for the specified websites.
     *
     * @param $idSites int Id Site to update ts_created
     * @param string Date to set as creation date.
     *
     * @ignore
     */
    public function updateSiteCreatedTime($idSites, $minDateSql)
    {
        $idSites   = array_map('intval', $idSites);

        $query = "UPDATE " . $this->table . " SET ts_created = ?" .
                " WHERE idsite IN ( " . implode(",", $idSites) . " ) AND ts_created > ?";

        $bind  = array($minDateSql, $minDateSql);

        $this->db->query($query, $bind);
    }

    /**
     * Returns all used type ids (unique)
     * @return array of used type ids
     */
    public function getUsedTypeIds()
    {
        $types = array();
        $rows = $this->db->fetchAll("SELECT DISTINCT `type` as typeid FROM " . $this->table);

        foreach ($rows as $row) {
            $types[] = $row['typeid'];
        }

        return $types;
    }

    /**
     * Insert the list of alias URLs for the website.
     * The URLs must not exist already for this website!
     */
    public function insertSiteUrl($idSite, $url)
    {
        $this->db->insert(Common::prefixTable("site_url"), array(
                'idsite' => (int) $idSite,
                'url'    => $url
            )
        );
    }

    public function getPatternMatchSites($ids, $pattern, $limit)
    {
        $ids_str = '';
        foreach ($ids as $id_val) {
            $ids_str .= (int) $id_val . ' , ';
        }
        $ids_str .= (int) $id_val;

        $bind = array('%' . $pattern . '%', 'http%' . $pattern . '%', '%' . $pattern . '%');

        // Also match the idsite
        $where = '';
        if (is_numeric($pattern)) {
            $bind[] = $pattern;
            $where  = 'OR s.idsite = ?';
        }

        $query = "SELECT *
                  FROM " . $this->table . " s
                  WHERE (    s.name like ?
                          OR s.main_url like ?
                          OR s.`group` like ?
                          $where )
                     AND idsite in ($ids_str)";

        if ($limit !== false) {
            $query .= " LIMIT " . (int) $limit;
        }

        $sites = $this->db->fetchAll($query, $bind);

        return $sites;
    }

    /**
     * Delete all the alias URLs for the given idSite.
     */
    public function deleteSiteAliasUrls($idsite)
    {
        $this->db->query("DELETE FROM " . Common::prefixTable("site_url") . " WHERE idsite = ?", $idsite);
    }
}
