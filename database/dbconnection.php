<?php 

/**
 * MySQLiDatabase Helper File
 *
 * @package	MySQLiDatabase
 * @subpackage	dbconnect.php
 * @category	Helper
 * @author(s)	Shivasis Biswal / Sasmita Biswal
 * @useful 	MySQLi Database
 */

// Define constants
const DB_HOST = 'localhost'; 		// Database host
const DB_USER = 'root';			// Database username
const DB_PASS = 'your_password';	// Database password
const DB_NAME = 'your_database_name';	// Database name

// Create connection
$con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$con) {
	die("Connection failed: " . mysqli_connect_error());
}


function getDbConnection() {
    // Use constants for connection parameters
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}


function buildWhereClause($conditions) {
    if (empty($conditions)) {
        return ['', []];
    }

    $where = [];
    $values = [];
    $defaultConjunction = 'AND'; // Default conjunction

    foreach ($conditions as $key => $value) {
        $operator = '='; // Default operator
        $conjunction = $defaultConjunction;

        // Extract column name and operator/conjunction
        $column = $key;
        $isOrCondition = false;

        // Handle 'OR' conjunction
        if (stripos($key, ':or') !== false) {
            $column = str_ireplace(':or', '', $key);
            $isOrCondition = true;
            $conjunction = 'OR';
        }

        // Handle custom operators (e.g., >=, <=, !=, >, <)
        if (preg_match('/([a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*)([<>!=]{1,2})$/', $column, $matches)) {
            $column = $matches[1];
            $operator = $matches[2];
        }

        // Handle NULL conditions (e.g., status:null, status:!null)
        if (preg_match('/([a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*):(!?null)$/i', $column, $matches)) {
            $column = $matches[1];
            $nullCondition = strtoupper($matches[2]);
            $where[] = "$column IS " . ($nullCondition === 'NULL' ? 'NULL' : 'NOT NULL');
            continue; // Skip adding conjunction for NULL conditions
        }

        // Validate column name to allow for table prefixes
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $column)) {
            throw new InvalidArgumentException("Invalid column name: $column");
        }

        // Handle IN clause with multiple values
        if (is_array($value)) {
            $placeholders = implode(', ', array_fill(0, count($value), '?'));
            $where[] = "$column IN ($placeholders)";
            $values = array_merge($values, $value);
            continue; // Skip adding conjunction for IN conditions
        }

        // Default to '=' or custom operator
        $where[] = "$column $operator ?";
        $values[] = $value;

        // Add conjunction if not the last condition
        if (next($conditions) !== false) {
            $where[] = $conjunction;
        }
    }

    // Remove the last conjunction if it exists
    if (end($where) === 'AND' || end($where) === 'OR') {
        array_pop($where);
    }

    $finalWhereClause = implode(' ', $where);
    return [$finalWhereClause, $values];
}


/**
 * Build a JOIN clause for SQL queries.
 * @param array $joins An array of join configurations.
 * Each join configuration should be an array in the format: [table, condition, type].
 * Example: ['users', 'users.id = orders.user_id', 'INNER']
 * @return string The constructed JOIN clause.
 * @throws InvalidArgumentException If the join configuration is invalid.
 */
function buildJoinClause($joins = []) {
    if (empty($joins)) {
        return '';
    }

    // Ensure $joins is an array of arrays
    if (!is_array($joins[0])) {
        $joins = [$joins];
    }

    $joinString = '';
    foreach ($joins as $join) {
        // Validate join configuration
        if (!is_array($join) || count($join) !== 3) {
            throw new InvalidArgumentException("Invalid join configuration. Expected format: [table, condition, type]");
        }

        list($join_table, $condition, $type) = $join;

        // Validate table name
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $join_table)) {
            throw new InvalidArgumentException("Invalid table name: $join_table");
        }

        // Validate join type
        $validJoinTypes = ['INNER', 'LEFT', 'RIGHT', 'FULL', 'CROSS'];
        $type = strtoupper($type);
        if (!in_array($type, $validJoinTypes)) {
            throw new InvalidArgumentException("Invalid join type: $type");
        }

        // Append to the join string
        $joinString .= "$type JOIN $join_table ON $condition ";
    }

    return trim($joinString);
}



function executeStatement($sql, $types, $params) {
    $conn = getDbConnection();
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    $success = $stmt->execute();

    $stmt->close();
    $conn->close();

    return $success;
}

function executeTransaction($queries) {
    $conn = getDbConnection();
    $conn->begin_transaction();

    try {
        foreach ($queries as $query) {
            if (!$conn->query($query)) {
                throw new Exception("Query failed: " . $conn->error);
            }
        }
        $conn->commit();
        $conn->close();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        $conn->close();
        throw $e;
    }
}

function selectData($table, $conditions = [], $asArray = false, $joins = [], $columns = '*') {
    $conn = getDbConnection();
	
   if(!$table) return false;

    // Build the query
    $sql = "SELECT $columns FROM $table";
    
    if (!empty($joins)) {
        $joinClause = buildJoinClause($joins);
        $sql .= " $joinClause";
    }
    
    if (!empty($conditions)) {
        list($whereClause, $values) = buildWhereClause($conditions);
        $sql .= " WHERE $whereClause";
    } else {
        $values = [];
    }

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    // Dynamically determine types for bind_param
	if (!empty($conditions)) {
    	$types = '';
		$params = [];
		if (!empty($values) && is_array($values)) {
			foreach ($values as $value) {
				if (is_int($value)) {
					$types .= 'i';
				} elseif (is_double($value)) {
					$types .= 'd';
				} else {
					$types .= 's';
				}
				$params[] = $value;
			}
			$stmt->bind_param($types, ...$params);
		}
	}

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_object()) {
            $rows[] = $asArray ? (array) $row : $row;
        }
    }

    $stmt->close();
    $conn->close();

    return $rows;
}


// insert data without column filters
function insertData($table, $data) {
    $conn = getDbConnection();

   if(!$table) return false;
   if(empty($data)) return false;
	
	// Sanitize data
    $sanitized_data = [];
    foreach ($data as $key => $value) {
       $sanitized_data[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    $columns = implode(", ", array_keys($sanitized_data));
    $placeholders = implode(", ", array_fill(0, count($sanitized_data), '?'));
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

    $stmt = $conn->prepare($sql);

    $types = '';
    foreach ($sanitized_data as $value) {
        $types .= (is_int($value)) ? 'i' : ((is_double($value)) ? 'd' : 's');
    }
    $stmt->bind_param($types, ...array_values($sanitized_data));

    $success = $stmt->execute();
    
    $lastInsertId = $success ? $conn->insert_id : null;

    $stmt->close();
    $conn->close();

    return $lastInsertId;
}


// insert data with column filters
function insertDataWithFilter($table, $data) {
    $conn = getDbConnection();

   if(!$table) return false;
   if(empty($data)) return false;
	
    // Sanitize data
    $sanitized_data = [];
    foreach ($data as $key => $value) {
       $sanitized_data[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    // Fetch column names from the table
   	$table_columns = getTableColumns($table);

    // Filter $data to include only keys that exist in $table_columns
    $filtered_data = array_filter($sanitized_data, function($key) use ($table_columns) {
        return in_array($key, $table_columns);
    }, ARRAY_FILTER_USE_KEY);

    $columns = implode(", ", array_keys($filtered_data));
    $placeholders = implode(", ", array_fill(0, count($filtered_data), '?'));
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

    $stmt = $conn->prepare($sql);

    $types = '';
    foreach ($filtered_data as $value) {
        $types .= (is_int($value)) ? 'i' : ((is_double($value)) ? 'd' : 's');
    }
    $stmt->bind_param($types, ...array_values($filtered_data));

    $success = $stmt->execute();
    
    $lastInsertId = $success ? $conn->insert_id : null;

    $stmt->close();
    $conn->close();

    return $lastInsertId;
}


// update data without column filters
function updateData($table, $data, $conditions) {
    $conn = getDbConnection();

    if(!$table) return false;
    if(empty($data)) return false;
	
	// Sanitize data
    $sanitized_data = [];
    foreach ($data as $key => $value) {
       $sanitized_data[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
	
    // Build the SET clause for the data
    $set = [];
    foreach ($sanitized_data as $column => $value) {
        $set[] = "$column = ?";
    }
    $setString = implode(", ", $set);

    // Build the WHERE clause for the conditions
    $where = [];
    foreach ($conditions as $column => $value) {
        $where[] = "$column = ?";
    }
    $whereString = implode(" AND ", $where);

    $sql = "UPDATE $table SET $setString WHERE $whereString";

    $stmt = $conn->prepare($sql);

    // Combine data and conditions
    $params = array_merge(array_values($sanitized_data), array_values($conditions));
    $types = '';
    foreach ($params as $value) {
        $types .= (is_int($value)) ? 'i' : ((is_double($value)) ? 'd' : 's');
    }
    $stmt->bind_param($types, ...$params);

    $success = $stmt->execute(); 
    $affectedRows = $stmt->affected_rows;

    $stmt->close();
    $conn->close();

    return $affectedRows;
}


// update data with column filters
function updateDataWithFilter($table, $data, $conditions) {
    $conn = getDbConnection();

   if(!$table) return false;
   if(empty($data)) return false;
	
	// Sanitize data
    $sanitized_data = [];
    foreach ($data as $key => $value) {
       $sanitized_data[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    // Fetch column names from the table
    $table_columns = getTableColumns($table);

    // Filter $data to include only keys that exist in $table_columns
    $filtered_data = array_filter($sanitized_data, function($key) use ($table_columns) {
        return in_array($key, $table_columns);
    }, ARRAY_FILTER_USE_KEY);

    // Build the SET clause for the data
    $set = [];
    foreach ($filtered_data as $column => $value) {
        $set[] = "$column = ?";
    }
    $setString = implode(", ", $set);

    // Build the WHERE clause for the conditions
    $where = [];
    foreach ($conditions as $column => $value) {
        $where[] = "$column = ?";
    }
    $whereString = implode(" AND ", $where);

    $sql = "UPDATE $table SET $setString WHERE $whereString";

    $stmt = $conn->prepare($sql);

    // Combine filtered data and conditions
    $params = array_merge(array_values($filtered_data), array_values($conditions));
    $types = '';
    foreach ($params as $value) {
        $types .= (is_int($value)) ? 'i' : ((is_double($value)) ? 'd' : 's');
    }
    $stmt->bind_param($types, ...$params);

    $success = $stmt->execute();
    $affectedRows = $stmt->affected_rows;

    $stmt->close();
    $conn->close();

    return $affectedRows;
}


function deleteData($table, $conditions) {
    $conn = getDbConnection();

    if(!$table) return false;

    // Build the WHERE clause for the conditions
    $where = [];
    foreach ($conditions as $column => $value) {
        $where[] = "$column = ?";
    }
    $whereString = implode(" AND ", $where);

    $sql = "DELETE FROM $table WHERE $whereString";

    $stmt = $conn->prepare($sql);

    $types = '';
    foreach ($conditions as $value) {
        $types .= (is_int($value)) ? 'i' : ((is_double($value)) ? 'd' : 's');
    }
    $stmt->bind_param($types, ...array_values($conditions));

    $success = $stmt->execute();
    $affectedRows = $stmt->affected_rows;

    $stmt->close();
    $conn->close();

    return $affectedRows;
}

function getTableColumns($table) {
    $conn = getDbConnection();

    if(!$table) return false;

    $columns = [];
    $result = $conn->query("DESCRIBE $table");
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    $conn->close();
    return $columns;
}

function getLastInsertedId($conn) {
    return $conn->insert_id;
}

function getLastAffectedRows($conn) {
    return $conn->affected_rows;
}


/**
 * Get the maximum value of a column in a table, optionally filtered by conditions.
 * @param string $table The table name.
 * @param string $column The column name.
 * @param array $conditions Optional conditions to filter the results.
 * @return mixed The maximum value or null if no rows are found.
 */
function getMaxValue($table, $column, $conditions = []) {
    $conn = getDbConnection();
    
    if (!$table || empty($column)) {
        return null;
    }

    // Build the base SQL query
    $sql = "SELECT MAX($column) AS max_value FROM $table";

    // Add WHERE clause if conditions are provided
    $values = [];
    if (!empty($conditions)) {
        list($whereClause, $values) = buildWhereClause($conditions);
        $sql .= " WHERE $whereClause";
    }

    // Prepare the SQL statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    // Bind parameters if there are conditions
    if (!empty($conditions) && !empty($values)) {
        $types = '';
        $params = [];
        foreach ($values as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_double($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $params[] = $value;
        }
        $stmt->bind_param($types, ...$params);
    }

    // Execute the query
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch the result
    $maxValue = null;
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $maxValue = $row['max_value'];
    }

    // Clean up
    $stmt->close();
    $conn->close();

    return $maxValue;
}

/**
 * Get the minimum value of a column in a table, optionally filtered by conditions.
 * @param string $table The table name.
 * @param string $column The column name.
 * @param array $conditions Optional conditions to filter the results.
 * @return mixed The minimum value or null if no rows are found.
 */
function getMinValue($table, $column, $conditions = []) {
    $conn = getDbConnection();
    
    if (!$table || empty($column)) {
        return null;
    }

    // Build the base SQL query
    $sql = "SELECT MIN($column) AS min_value FROM $table";

    // Add WHERE clause if conditions are provided
    $values = [];
    if (!empty($conditions)) {
        list($whereClause, $values) = buildWhereClause($conditions);
        $sql .= " WHERE $whereClause";
    }

    // Prepare the SQL statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    // Bind parameters if there are conditions
    if (!empty($conditions) && !empty($values)) {
        $types = '';
        $params = [];
        foreach ($values as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_double($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $params[] = $value;
        }
        $stmt->bind_param($types, ...$params);
    }

    // Execute the query
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch the result
    $minValue = null;
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $minValue = $row['min_value'];
    }

    // Clean up
    $stmt->close();
    $conn->close();

    return $minValue;
}

function getRow($table, $conditions = [], $asArray = false, $joins = [], $columns = '*') {
    $conn = getDbConnection();
	
	if(!$table) return false;
    
    $sql = "SELECT $columns FROM $table";

    // Add JOIN clauses if provided
    if (!empty($joins)) {
        $joinClause = buildJoinClause($joins);
        $sql .= " $joinClause";
    }

    // Build WHERE clause if conditions are provided
    if (!empty($conditions)) {
        list($whereClause, $values) = buildWhereClause($conditions);
        $sql .= " WHERE $whereClause";
    } else {
        $values = [];
    }

    $stmt = $conn->prepare($sql);

    // Dynamically bind parameters if there are conditions
    if (!empty($conditions)) {
        $types = '';
		$params = [];
        if (!empty($values) && is_array($values)) {
            foreach ($values as $value) {
                if (is_int($value)) {
                    $types .= 'i';
                } elseif (is_double($value)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
				$params[] = $value;
            }
            $stmt->bind_param($types, ...$params);
        }
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $row = $result->num_rows > 0 ? $result->fetch_object() : null;  // Fetch as object if found

    $stmt->close();
    $conn->close();

    if ($row) {
        return $asArray ? (array) $row : $row;  // Convert object to array if needed
    } else {
        return null;
    }
}


function getRows($table, $conditions = [], $asArray = false, $joins = [], $columns = '*') {
    $conn = getDbConnection();
    
    if (!$table) {
        return false;
    }
    
    // Build the base SQL query
    $sql = "SELECT $columns FROM $table";

    // Add JOIN clauses if provided
    if (!empty($joins)) {
        $joinClause = buildJoinClause($joins);
        $sql .= " $joinClause";
    }

    // Build WHERE clause if conditions are provided
    $values = [];
    if (!empty($conditions)) {
        list($whereClause, $values) = buildWhereClause($conditions);
        $sql .= " WHERE $whereClause";
    }

    // Debugging output (remove in production)
    echo "SQL Query: $sql\n";
    if (!empty($conditions)) {
        echo "WHERE Clause: $whereClause\n";
        print_r($values);
    }

    // Prepare the SQL statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    // Dynamically bind parameters if there are conditions and values
    if (!empty($conditions) && !empty($values)) {
        $types = '';
        $params = [];
        foreach ($values as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_double($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $params[] = $value;
        }
        $stmt->bind_param($types, ...$params);
    }

    // Execute the query
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch the rows
    $rows = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_object()) {
            $rows[] = $asArray ? (array) $row : $row;
        }
    }

    // Clean up
    $stmt->close();
    $conn->close();

    return $rows;
}


function parseRawQuery($rawSql) {
    // Get database connection
    $conn = getDbConnection();
    if (!$conn) {
        throw new Exception("Failed to connect to the database.");
    }

    // Determine the SQL operation (e.g., INSERT, UPDATE, DELETE, SELECT)
    $operation = strtoupper(strtok($rawSql, " "));

    try {
        // Handle the query based on the operation
        if (stripos($operation, 'INSERT') === 0) {
            return handleInsertQuery($conn, $rawSql);
        } elseif (stripos($operation, 'UPDATE') === 0) {
            return handleUpdateQuery($conn, $rawSql);
        } elseif (stripos($operation, 'DELETE') === 0) {
            return handleDeleteQuery($conn, $rawSql);
        } elseif (stripos($operation, 'SELECT') === 0) {
            return handleSelectQuery($conn, $rawSql);
        } else {
            throw new Exception("Unsupported SQL operation: $operation");
        }
    } catch (Exception $e) {
        error_log("Error executing query: " . $e->getMessage());
        return false;
    } finally {
        // Close the database connection
        $conn->close();
    }
}

function handleInsertQuery($conn, $sql) {
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
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

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

    $stmt->bind_param($types, ...$params);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function handleUpdateQuery($conn, $sql) {
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
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

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
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function handleDeleteQuery($conn, $sql) {
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
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

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
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function handleSelectQuery($conn, $sql) {
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
    $stmt = $conn->prepare($sql);
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

?>
