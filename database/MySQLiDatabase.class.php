<?php 

/**
 * MySQLiDatabase Class File
 *
 * @package	MySQLiDatabase
 * @subpackage	MySQLiDatabase.class.php
 * @category	Helper
 * @author(s)	Shivasis Biswal / Sasmita Biswal
 * @useful 	MySQLi Database
 */

class MySQLiDatabase {
    private static $instance = null;
    private $conn;

    // Private constructor to prevent direct instantiation
    private function __construct($dbhost, $dbuser, $dbpass, $dbname) {
        $this->conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
        if ($this->conn->connect_error) {
            throw new Exception("Connection failed: " . $this->conn->connect_error);
        }
    }

    // Get the singleton instance
    public static function getInstance($dbhost, $dbuser, $dbpass, $dbname) {
        if (self::$instance === null) {
            self::$instance = new MyDatabase($dbhost, $dbuser, $dbpass, $dbname);
        }
        return self::$instance;
    }

    // Get the database connection
    private function getConnection() {
        return $this->conn;
    }

    // Build WHERE clause for queries
	private function buildWhereClause($conditions) {
	    if (empty($conditions)) {
	        return ['', []];
	    }
	
	    $where = [];
	    $values = [];
	    $defaultConjunction = 'AND'; // Default conjunction
	
	    foreach ($conditions as $key => $value) {
	        $operator = '='; // Default operator
	        $conjunction = $defaultConjunction;
	
	        // Handle 'OR' conjunction
	        if (stripos($key, ':or') !== false) {
	            $column = str_ireplace(':or', '', $key);
	            $conjunction = 'OR';
	        } else {
	            $column = $key;
	        }
	
	        // Handle IS NULL and IS NOT NULL
	        if (preg_match('/(.*):(!?null)$/i', $column, $matches)) {
	            $column = $matches[1];
	            $nullCondition = strtoupper($matches[2]);
	            $where[] = "$column IS " . ($nullCondition === 'NULL' ? 'NULL' : 'NOT NULL');
	            continue; // Skip adding conjunction for NULL conditions
	        }
	
	        // Handle IN clause with multiple values
	        if (is_array($value)) {
	            $placeholders = implode(', ', array_fill(0, count($value), '?'));
	            $where[] = "$column IN ($placeholders)";
	            $values = array_merge($values, $value);
	            continue; // Skip adding conjunction for IN conditions
	        }
	
	        // Handle custom operators
	        if (preg_match('/(.*)([<>!=]{1,2})$/', $column, $matches)) {
	            $column = $matches[1];
	            $operator = $matches[2];
	        }
	
	        // Default to '='
	        $where[] = "$column $operator ?";
	        $values[] = $value;
	
	        // Add conjunction if not the last condition
	        if (next($conditions) !== false) {
	            $where[] = $conjunction;
	        }
	    }
	
	    $finalWhereClause = implode(' ', $where);
	    return [$finalWhereClause, $values];
	}

    // Execute a prepared statement
    private function executeStatement($sql, $types, $params) {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $this->conn->error);
        }
        $stmt->bind_param($types, ...$params);

        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    // Execute a transaction
    public function executeTransaction($queries) {
        $this->conn->begin_transaction();

        try {
            foreach ($queries as $query) {
                if (!$this->conn->query($query)) {
                    throw new Exception("Query failed: " . $this->conn->error);
                }
            }
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    // Select data from a table
    public function selectData($table, $conditions = [], $asArray = false, $join = '', $columns = '*') {
        // Build the query
        $sql = "SELECT $columns FROM $table";
        if (!empty($join)) $sql .= " $join";
        if (!empty($conditions)) {
            list($whereClause, $values) = $this->buildWhereClause($conditions);
            $sql .= " WHERE $whereClause";
        }

        $stmt = $this->conn->prepare($sql);

        // Dynamically determine types for bind_param
        $types = '';
        foreach ($values as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_double($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }

        if (!empty($conditions)) {
            $stmt->bind_param($types, ...$values);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_object()) {
                $rows[] = $row;
            }

            if ($asArray) {
                // Convert each object to an array
                $rows = array_map(function($obj) {
                    return (array) $obj;
                }, $rows);
            }
        }

        $stmt->close();

        return $rows;
    }

    // Insert data into a table
    public function insertData($table, $data) {
        if (empty($data)) return false;

        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

        $stmt = $this->conn->prepare($sql);

        $types = '';
        foreach ($data as $value) {
            $types .= (is_int($value)) ? 'i' : ((is_double($value)) ? 'd' : 's');
        }
        $stmt->bind_param($types, ...array_values($data));

        $success = $stmt->execute();
        $lastInsertId = $success ? $this->conn->insert_id : null;

        $stmt->close();

        return $lastInsertId;
    }

    // Update data in a table
    public function updateData($table, $data, $conditions) {
        if (empty($data)) return false;

        // Build the SET clause for the data
        $set = [];
        foreach ($data as $column => $value) {
            $set[] = "$column = ?";
        }
        $setString = implode(", ", $set);

        // Build the WHERE clause for the conditions
        list($whereClause, $conditionValues) = $this->buildWhereClause($conditions);

        $sql = "UPDATE $table SET $setString WHERE $whereClause";

        // Combine data and conditions
        $params = array_merge(array_values($data), $conditionValues);
        $types = '';
        foreach ($params as $value) {
            $types .= (is_int($value)) ? 'i' : ((is_double($value)) ? 'd' : 's');
        }

        return $this->executeStatement($sql, $types, $params);
    }

    // Delete data from a table
    public function deleteData($table, $conditions) {
        // Build the WHERE clause for the conditions
        list($whereClause, $values) = $this->buildWhereClause($conditions);

        $sql = "DELETE FROM $table WHERE $whereClause";

        $types = '';
        foreach ($values as $value) {
            $types .= (is_int($value)) ? 'i' : ((is_double($value)) ? 'd' : 's');
        }

        return $this->executeStatement($sql, $types, $values);
    }

    // Get the last inserted ID
    public function getLastInsertedId() {
        return $this->conn->insert_id;
    }

    // Get the maximum value of a column
    public function getMaxValue($table, $column) {
        if (empty($column)) return false;

        $sql = "SELECT MAX($column) AS max_value FROM $table";
        $result = $this->conn->query($sql);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['max_value'];
        } else {
            return null;
        }
    }

    // Get the minimum value of a column
    public function getMinValue($table, $column) {
        if (empty($column)) return false;

        $sql = "SELECT MIN($column) AS min_value FROM $table";
        $result = $this->conn->query($sql);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['min_value'];
        } else {
            return null;
        }
    }

    // Get a single row from a table
    public function getRow($table, $conditions = [], $asArray = false, $join = '', $columns = '*') {
        $rows = $this->selectData($table, $conditions, $asArray, $join, $columns);
        return !empty($rows) ? $rows[0] : null;
    }

    // Get multiple rows from a table
    public function getRows($table, $conditions = [], $asArray = false, $join = '', $columns = '*') {
        return $this->selectData($table, $conditions, $asArray, $join, $columns);
    }

    // Parse and execute a raw SQL query
    public function parseRawQuery($rawSql) {
        // Determine the SQL operation (e.g., INSERT, UPDATE, DELETE, SELECT)
        $operation = strtoupper(strtok($rawSql, " "));

        try {
            // Handle the query based on the operation
            if (stripos($operation, 'INSERT') === 0) {
                return $this->handleInsertQuery($rawSql);
            } elseif (stripos($operation, 'UPDATE') === 0) {
                return $this->handleUpdateQuery($rawSql);
            } elseif (stripos($operation, 'DELETE') === 0) {
                return $this->handleDeleteQuery($rawSql);
            } elseif (stripos($operation, 'SELECT') === 0) {
                return $this->handleSelectQuery($rawSql);
            } else {
                throw new Exception("Unsupported SQL operation: $operation");
            }
        } catch (Exception $e) {
            error_log("Error executing query: " . $e->getMessage());
            return false;
        }
    }

    // Handle INSERT queries
    private function handleInsertQuery($sql) {
        // Extract columns and values from the INSERT query
        $columnsStart = strpos($sql, '(') + 1;
        $columnsEnd = strpos($sql, ')');
        $columns = explode(',', substr($sql, $columnsStart, $columnsEnd - $columnsStart));

        $valuesStart = stripos($sql, 'VALUES (') + 8;
        $valuesEnd = strpos($sql, ')', $valuesStart);
        $values = explode(',', substr($sql, $valuesStart, $valuesEnd - $valuesStart));

        // Prepare the SQL statement with placeholders
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $sql = "INSERT INTO " . substr($sql, 12, $columnsStart - 13) . " (" . implode(',', $columns) . ") VALUES ($placeholders)";

        // Bind parameters and execute
        $types = '';
        $params = [];
        foreach ($values as $value) {
            $value = trim($value, "' ");
            if (is_numeric($value)) {
                $types .= is_int($value + 0) ? 'i' : 'd';
            } else {
                $types .= 's';
            }
            $params[] = $value;
        }

        return $this->executeStatement($sql, $types, $params);
    }

    // Handle UPDATE queries
    private function handleUpdateQuery($sql) {
        // Extract SET and WHERE clauses
        $setStart = stripos($sql, 'SET ') + 4;
        $whereStart = stripos($sql, 'WHERE');
        $setPart = $whereStart !== false ? substr($sql, $setStart, $whereStart - $setStart) : substr($sql, $setStart);

        // Parse columns and values from the SET clause
        $columns = [];
        $values = [];
        foreach (explode(',', $setPart) as $pair) {
            list($column, $value) = explode('=', $pair);
            $columns[] = trim($column);
            $values[] = trim($value, "' ");
        }

        // Parse WHERE clause (if present)
        $whereParams = [];
        if ($whereStart !== false) {
            $whereClause = substr($sql, $whereStart + 6);
            foreach (explode('AND', $whereClause) as $condition) {
                list($column, $value) = explode('=', $condition);
                $columns[] = trim($column);
                $values[] = trim($value, "' ");
                $whereParams[] = trim($value, "' ");
            }
        }

        // Prepare the SQL statement with placeholders
        $setPlaceholders = implode('=?,', $columns) . '=?';
        $sql = "UPDATE " . substr($sql, 7, $setStart - 8) . " SET $setPlaceholders";
        if ($whereStart !== false) {
            $sql .= " WHERE " . str_replace('=', '=?', $whereClause);
        }

        // Bind parameters and execute
        $types = '';
        $params = [];
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $types .= is_int($value + 0) ? 'i' : 'd';
            } else {
                $types .= 's';
            }
            $params[] = $value;
        }

        return $this->executeStatement($sql, $types, $params);
    }

    // Handle DELETE queries
    private function handleDeleteQuery($sql) {
        // Extract WHERE clause
        $whereStart = stripos($sql, 'WHERE');
        if ($whereStart === false) {
            throw new Exception("DELETE query must have a WHERE clause.");
        }

        $whereClause = substr($sql, $whereStart + 6);
        $columns = [];
        $values = [];
        foreach (explode('AND', $whereClause) as $condition) {
            list($column, $value) = explode('=', $condition);
            $columns[] = trim($column);
            $values[] = trim($value, "' ");
        }

        // Prepare the SQL statement with placeholders
        $sql = "DELETE FROM " . substr($sql, 12, $whereStart - 13) . " WHERE " . str_replace('=', '=?', $whereClause);

        // Bind parameters and execute
        $types = '';
        $params = [];
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $types .= is_int($value + 0) ? 'i' : 'd';
            } else {
                $types .= 's';
            }
            $params[] = $value;
        }

        return $this->executeStatement($sql, $types, $params);
    }

    // Handle SELECT queries
    private function handleSelectQuery($sql) {
        // Extract WHERE clause (if present)
        $whereStart = stripos($sql, 'WHERE');
        $values = [];
        if ($whereStart !== false) {
            $whereClause = substr($sql, $whereStart + 6);
            foreach (explode('AND', $whereClause) as $condition) {
                list($column, $value) = explode('=', $condition);
                $values[] = trim($value, "' ");
            }
        }

        // Prepare the SQL statement with placeholders
        if ($whereStart !== false) {
            $sql = substr($sql, 0, $whereStart) . " WHERE " . str_replace('=', '=?', $whereClause);
        }

        // Bind parameters and execute
        $types = '';
        $params = [];
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $types .= is_int($value + 0) ? 'i' : 'd';
            } else {
                $types .= 's';
            }
            $params[] = $value;
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    if (!empty($values)) {
        $types = '';
        $params = [];
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $types .= is_int($value + 0) ? 'i' : 'd';
            } else {
                $types .= 's';
            }
            $params[] = $value;
        }
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_object()) {
            $rows[] = $row;
        }
    }
    $stmt->close();

    return $rows;
	}
}
