<?php
class d_Mailschedule {

    private $filters;
    private static $instance;

    /**
     * Creating a new instance of d_Mailschedule class.
     * Private constructor to prevent creating an additional instance of this class.
     */
    private function __construct()
    {
        $this->filters = array();
    }

    /**
     * Get the instance or create a new instance if no instance already exist.
     *
     * @return d_Mailschedule
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new d_Mailschedule();
        }

        return self::$instance;
    }

    /**
     * Create Mailschedule table.
     */
    public function Create()
    {
        $queryResult = DB::getDBConnection()->query('CREATE TABLE IF NOT EXISTS `mailschedule` (
 `mailscheduleid` INT(11) NOT NULL AUTO_INCREMENT,
 `sendafter` DATETIME NOT NULL,
 `to` VARCHAR(255) NOT NULL COLLATE \'utf8_unicode_ci\',
 `subject` VARCHAR(255) NOT NULL COLLATE \'utf8_unicode_ci\',
 `arguments` VARCHAR(255) NOT NULL COLLATE \'utf8_unicode_ci\',
 `headers` MEDIUMTEXT NOT NULL COLLATE \'utf8_unicode_ci\',
 `body` LONGTEXT NOT NULL COLLATE \'utf8mb4_unicode_ci\',
 PRIMARY KEY (`mailscheduleid`) USING BTREE
)
COMMENT=\'BrachioMailer scheduled emails\'
COLLATE=\'utf8mb4_unicode_ci\'
ENGINE=InnoDB;');
        if (!$queryResult) {
            throw new Exception('Error Create query.');
        }
    }

    /**
     * Add a Mailschedule.
     *
     * @param string $sendafter      (..)
     * @param string $to             (..)
     * @param string $subject        (..)
     * @param string $arguments      (..)
     * @param string $headers        (..)
     * @param string $body           (..)
     * @return bool True if successfully added to database.
     */
    public function Add($sendafter, $to, $subject, $arguments, $headers, $body)
    {
        $stmtadd = DB::getDBConnection()->prepare('INSERT INTO mailschedule (`sendafter`, `to`, `subject`, `arguments`, `headers`, `body`)
 VALUES (:sendafter, :to, :subject, :arguments, :headers, :body);');
        $stmtadd->bindParam(':sendafter', $sendafter, PDO::PARAM_STR);
        $stmtadd->bindParam(':to', $to, PDO::PARAM_STR);
        $stmtadd->bindParam(':subject', $subject, PDO::PARAM_STR);
        $stmtadd->bindParam(':arguments', $arguments, PDO::PARAM_STR);
        $stmtadd->bindParam(':headers', $headers, PDO::PARAM_STR);
        $stmtadd->bindParam(':body', $body, PDO::PARAM_STR);
        return $stmtadd->execute();
    }

    /**
     * Count the total number of records in the mailschedule table.
     *
     * @return int Number of records.
     */
    public function Count()
    {
        $numfilters = count($this->filters);
        $qrycount = 'SELECT COUNT(mailscheduleid) AS c FROM mailschedule';
        if ($numfilters >= 1) {
            $qrycount .= ' WHERE ';
            for ($i = 0; $i < $numfilters; ++$i) {
                if ($i >= 1) {
                    $qrycount .= ' AND ';
                }

                $qrycount .= '`'.$this->filters[$i]['field'].'` '.$this->filters[$i]['operator'].' :'.$this->filters[$i]['field'].CHRENTER;
            }
        }

        $qrycount .= ' LIMIT 1;';
        $stmtcnt = DB::getDBConnection()->prepare($qrycount);
        if ($numfilters >= 1) {
            for ($i = 0; $i < $numfilters; ++$i) {
                $stmtcnt->bindParam(':'.$this->filters[$i]['field'], $this->filters[$i]['value']); 
            }
        }

        if (!$stmtcnt->execute()) {
            throw new Exception('Error GetAll query.');
        } elseif ($numfilters >= 1) {
            $this->filters = array();
        }

        return (int)$stmtcnt->fetchColumn(0);
    }

    /**
     * Filter by a field.
     */
    public function Filter($fieldname, $searchvalue, $operator = '=')
    {
        if (empty($fieldname)) {
            throw new Exception('Field to apply a filter on cannot be empty or null.');
        }

        if ($operator !== '=' && $operator !== 'LIKE' &&
            $operator !== '<>' && $operator !== '!=' &&
            $operator !== '<' && $operator !== '>' &&
            $operator !== '<=' && $operator !== '>=' &&
            $operator !== '!<' && $operator !== '!>' && $operator !== '<=>') {
            throw new Exception('Unknown filter operator');
        }

        $filternamecount = 1;
        $numfilters = count($this->filters);
        for ($i = 0; $i < $numfilters; ++$i) {
            if ($this->filters[$i]['field'] === $fieldname) {
                ++$filternamecount;
            }
        }

        $newfilter = array('field' => $fieldname,
                           'operator' => $operator,
                           'value' => $searchvalue,
                           'filternamecount' => $filternamecount);
        $this->filters[] = $newfilter;
        return $this;
    }

    /**
     * Get a Mailschedule by primairy key.
     *
     * @param int $mailscheduleid (..)
     * @return mixed Mailschedule
     */
    public function Get($mailscheduleid)
    {
        if (!is_int($mailscheduleid)) {
            throw new Exception('Error mailscheduleid');
        }

        $stmtget = DB::getDBConnection()->prepare('SELECT * FROM mailschedule WHERE mailscheduleid = :mailscheduleid
 LIMIT 1;');
        $stmtget->bindParam(':mailscheduleid', $mailscheduleid, PDO::PARAM_INT);
        if (!$stmtget->execute()) {
            throw new Exception('Error Get query.');
        }

        return $stmtget->fetch();
    }

    /**
     * Get all Mailschedule's
     *
     * @param int $limit  The maximum number of records to return. Use PHP_INT_MAX by default.
     * @param int $offset The offset of the record position to start returning. Use 0 by default.
     * @return mixed[][]
     */
    public function GetAll($limit = PHP_INT_MAX, $offset = 0)
    {
        if (!is_int($offset)) {
            throw new Exception('Error offset');
        }

        if (!is_int($limit)) {
            throw new Exception('Error limit');
        }

        $numfilters = count($this->filters);
        if ($numfilters === 0) {
            $stmtgetall = DB::getDBConnection()->prepare('SELECT * FROM `mailschedule` LIMIT :limit OFFSET :offset;');
            $stmtgetall->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmtgetall->bindValue(':offset', $offset, PDO::PARAM_INT);
            if (!$stmtgetall->execute()) {
                throw new Exception('Error GetAll query.');
            }

            return $stmtgetall->fetchAll();
        } else {
            $qrygetall = 'SELECT * FROM `mailschedule` WHERE ';
            for ($i = 0; $i < $numfilters; ++$i) {
                if ($i >= 1) {
                    $qrygetall .= ' AND ';
                }

                $qrygetall .= '`'.$this->filters[$i]['field'].'` '.$this->filters[$i]['operator'].' :'.$this->filters[$i]['field'];
                if ($this->filters[$i]['filternamecount'] > 1) {
                    $qrygetall .= $this->filters[$i]['filternamecount'];
                }

                $qrygetall .= CHRENTER;
            }

            $qrygetall .= ' LIMIT :limit OFFSET :offset;';
            $stmtgetall = DB::getDBConnection()->prepare($qrygetall);
            for ($i = 0; $i < $numfilters; ++$i) {
                if ($this->filters[$i]['filternamecount'] > 1) {
                    $stmtgetall->bindParam(':'.$this->filters[$i]['field'].$this->filters[$i]['filternamecount'],
                                           $this->filters[$i]['value']);
                } else {
                    $stmtgetall->bindParam(':'.$this->filters[$i]['field'], $this->filters[$i]['value']);
                }
            }

            $stmtgetall->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmtgetall->bindValue(':offset', $offset, PDO::PARAM_INT);
            if (!$stmtgetall->execute()) {
                throw new Exception('Error GetAll query.');
            } else {
                $this->filters = array();
            }

            return $stmtgetall->fetchAll();
        }
    }

    /**
     * Update a Mailschedule
     *
     * @param int    $mailscheduleid (..)
     * @param string $sendafter      (..)
     * @param string $to             (..)
     * @param string $subject        (..)
     * @param string $arguments      (..)
     * @param string $headers        (..)
     * @param string $body           (..)
     * @return bool True if updated successfully.
     */
    public function Update($mailscheduleid, $sendafter, $to, $subject, $arguments, $headers, $body)
    {
        if (!is_int($mailscheduleid)) {
            throw new Exception('Error mailscheduleid');
        }

        $stmtupdate = DB::getDBConnection()->prepare('UPDATE mailschedule SET `sendafter` = :sendafter, `to` = :to, `subject` = :subject, `arguments` = :arguments, `headers` = :headers, `body` = :body
 WHERE `mailscheduleid` = :mailscheduleid
 LIMIT 1;');
        $stmtupdate->bindParam(':mailscheduleid', $mailscheduleid, PDO::PARAM_INT);
        $stmtupdate->bindParam(':sendafter', $sendafter, PDO::PARAM_STR);
        $stmtupdate->bindParam(':to', $to, PDO::PARAM_STR);
        $stmtupdate->bindParam(':subject', $subject, PDO::PARAM_STR);
        $stmtupdate->bindParam(':arguments', $arguments, PDO::PARAM_STR);
        $stmtupdate->bindParam(':headers', $headers, PDO::PARAM_STR);
        $stmtupdate->bindParam(':body', $body, PDO::PARAM_STR);
        return $stmtupdate->execute();
    }

    /**
     * Remove a Mailschedule
     * When there are relations with other entities a transaction must be used to keep guaranteeing proper relations.
     *
     * @param int $mailscheduleid (..)
     * @return bool True if successfully removed.
     */
    public function Remove($mailscheduleid)
    {
        if (!is_int($mailscheduleid)) {
            throw new Exception('Error mailscheduleid');
        }

        $stmtremove  = DB::getDBConnection()->prepare('DELETE FROM mailschedule WHERE mailscheduleid = :mailscheduleid
 LIMIT 1;');
        $stmtremove->bindParam(':mailscheduleid', $mailscheduleid, PDO::PARAM_INT);
        return $stmtremove->execute();
    }

    /**
     * Remove all Mailschedule from table.
     *
     * @return int The number of affected rows.
     */
    public function RemoveAll()
    {
        $affectedrows = 0;
        $numfilters = count($this->filters);
        if ($numfilters === 0) {
            $affectedrows  = DB::getDBConnection()->exec('TRUNCATE mailschedule');
            if ($affectedrows === false) {
                throw new Exception('Error RemoveAll query.');
            }
        } else {
            $qryremoveall = 'DELETE FROM mailschedule WHERE ';
            for ($i = 0; $i < $numfilters; ++$i) {
                if ($i >= 1) {
                    $qryremoveall .= ' AND ';
                }

                $qryremoveall .= '`'.$this->filters[$i]['field'].'` '.$this->filters[$i]['operator'].' :'.$this->filters[$i]['field'].CHRENTER;
            }

            $stmtremoveall = DB::getDBConnection()->prepare($qryremoveall);
            for ($i = 0; $i < $numfilters; ++$i) {
                $stmtremoveall->bindParam(':'.$this->filters[$i]['field'], $this->filters[$i]['value']); 
            }

            if (!$stmtremoveall->execute()) {
                throw new Exception('Error RemoveAll query.');
            } else {
                $this->filters = array();
            }

            $affectedrows = $stmtremoveall->rowCount();
        }

        return $affectedrows;
    }
}

