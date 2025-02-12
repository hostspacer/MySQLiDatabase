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

$dbhost = 'localhost';     // Database host
$dbuser = 'root';  // Database username
$dbpass = 'your_password';  // Database password
$dbname = 'your_database_name';    // Database name

// Create connection
$con = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// Check connection
if (!$con) {
	die("Connection failed: " . mysqli_connect_error());
}

function getDbConnection() {

	$dbhost = 'localhost';     // Database host
	$dbuser = 'root';  // Database username
	$dbpass = 'your_password';  // Database password
	$dbname = 'your_database_name';    // Database name
	
    $conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}


function buildWhereClause($conditions) {
    $where = [];
    $values = [];

    foreach ($conditions as $column => $condition) {
        if (is_array($condition)) {
            // Handle custom operators (e.g., ['>', 10])
            $operator = $condition[0];
            $value = $condition[1];
            $where[] = "$column $operator ?";
            $values[] = $value;
        } else {
            // Default to '='
            $where[] = "$column = ?";
            $values[] = $condition;
        }
    }

    $whereClause = implode(' AND ', $where);
    return [$whereClause, $values];
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

function selectData($table, $conditions = [], $asArray = false, $join = '', $columns = '*') {
    $conn = getDbConnection();

    // Build the query
    $sql = "SELECT $columns FROM $table";
    if (!empty($join)) $sql .= " $join";
    if (!empty($conditions)) {
        $whereClause = implode(' AND ', array_map(function($key) {
            return "$key = ?";
        }, array_keys($conditions)));
        $sql .= " WHERE $whereClause";
    }

    $stmt = $conn->prepare($sql);

    // Dynamically determine types for bind_param
    $types = '';
    foreach ($conditions as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_double($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }

    if (!empty($conditions)) {
        $stmt->bind_param($types, ...array_values($conditions));
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
    $conn->close();

    return $rows;
}




function insertData($table, $data) {
    $conn = getDbConnection();

    $columns = implode(", ", array_keys($data));
    $placeholders = implode(", ", array_fill(0, count($data), '?'));
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

    $stmt = $conn->prepare($sql);

    $types = '';
    foreach ($data as $value) {
        $types .= (is_int($value)) ? 'i' : ((is_double($value)) ? 'd' : 's');
    }
    $stmt->bind_param($types, ...array_values($data));

    $success = $stmt->execute();
    
    $lastInsertId = $success ? $conn->insert_id : null;

    $stmt->close();
    $conn->close();

    return $lastInsertId;
}


function updateData($table, $data, $conditions) {
    $conn = getDbConnection();

    // Build the SET clause for the data
    $set = [];
    foreach ($data as $column => $value) {
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
    $params = array_merge(array_values($data), array_values($conditions));
    $types = '';
    foreach ($params as $value) {
        $types .= (is_int($value)) ? 'i' : ((is_double($value)) ? 'd' : 's');
    }
    $stmt->bind_param($types, ...$params);

    $success = $stmt->execute();

    $stmt->close();
    $conn->close();

    return $success;
}

function deleteData($table, $conditions) {
    $conn = getDbConnection();

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

    $stmt->close();
    $conn->close();

    return $success;
}


function getLastInsertedId() {
    $conn = getDbConnection();
    $inserted_id = $conn->insert_id;
    $conn->close();
    return $inserted_id;
}

function getMaxValue($table, $column) {
    $conn = getDbConnection();
	
	if(empty($column)) return false;
    
    // SQL query to get the maximum value
    $sql = "SELECT MAX($column) AS max_value FROM $table";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['max_value'];
    } else {
        echo "No data found";
        return null;
    }

    $conn->close();
}

function getMinValue($table, $column) {
    $conn = getDbConnection();
	
	if(empty($column)) return false;
    
    // SQL query to get the minimum value
    $sql = "SELECT MIN($column) AS min_value FROM $table";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['min_value'];
    } else {
        echo "No data found";
        return null;
    }

    $conn->close();
}

function getRow($table, $conditions = [], $join = '', $columns = '*', $asArray = false) {
    $conn = getDbConnection();

    // Build WHERE clause if conditions are provided
    if (!empty($conditions)) {
        list($whereClause, $values) = buildWhereClause($conditions);
        $sql = !empty($join) ? "SELECT $columns FROM $table $join WHERE $whereClause" : "SELECT $columns FROM $table WHERE $whereClause";
    } else {
        $sql = !empty($join) ? "SELECT $columns FROM $table $join" : "SELECT $columns FROM $table";
        $values = [];
    }

    $stmt = $conn->prepare($sql);

    // Dynamically bind parameters if there are conditions
    if (!empty($conditions)) {
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
        $stmt->bind_param($types, ...$values);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $row = $result->num_rows > 0 ? $result->fetch_object() : null;  // Fetch as object if found

    $stmt->close();
    $conn->close();

    if ($row) {
        return $asArray ? (array) $row : $row;  // Convert object to array if needed
    } else {
        echo "No data found";
        return null;
    }
}

function getRows($table, $conditions = [], $join = '', $columns = '*', $asArray = false) {
    $conn = getDbConnection();

    // Build WHERE clause if conditions are provided
    if (!empty($conditions)) {
        list($whereClause, $values) = buildWhereClause($conditions);
        $sql = !empty($join) ? "SELECT $columns FROM $table $join WHERE $whereClause" : "SELECT $columns FROM $table WHERE $whereClause";
    } else {
        $sql = !empty($join) ? "SELECT $columns FROM $table $join" : "SELECT $columns FROM $table";
        $values = [];
    }

    $stmt = $conn->prepare($sql);

    // Dynamically bind parameters if there are conditions
    if (!empty($conditions)) {
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
        $stmt->bind_param($types, ...$values);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_object()) {
            $rows[] = $row;  // Fetch each row as an object
        }
    }

    $stmt->close();
    $conn->close();

    if (!empty($rows)) {
        return $asArray ? array_map(function($obj) { return (array) $obj; }, $rows) : $rows;  // Convert each object to an array if needed
    } else {
        echo "No data found";
        return null;
    }
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
