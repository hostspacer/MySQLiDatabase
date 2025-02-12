# MySQLiDatabase Use

### Explanation:

#### Create a Database Connection

Before using any methods, you need to include the ``` dbconnect.php ``` file by passing the required database connection credentials (host, user, password, and database name).

```php 
// Include the dbconnect file
require_once 'dbconnect.php';
```

#### Insert Data:
The function ```insertData``` inserts a new record into the specified table.

##### Usage Example:
```php
// Data to be inserted
$data = [
    'column1' => 'value1',
    'column2' => 'value2',
    // Add more columns and values as needed
];

// Insert data and get the last inserted ID
$lastInsertId = insertData('your_table_name', $data);

if ($lastInsertId) {
    echo "Data inserted successfully. Last Inserted ID: " . $lastInsertId;
} else {
    echo "Failed to insert data.";
}
```


#### Update Data:
The function ```updateData``` updates existing records in the specified table.

```php
// Usage example
$data = [
    'column1' => 'new_value1',
    'column2' => 100.50,
];

$conditions = [
    'id' => 42,
];

$updateSuccess = updateData('your_table_name', $data, $conditions);

if ($updateSuccess) {
    echo "Data updated successfully.";
} else {
    echo "Failed to update data.";
}
```

        
#### Delete Data:
The function ```deleteData``` deletes records from the specified table.

```php
// Usage example
$conditions = [
    'id' => 42,
];

$deleteSuccess = deleteData('your_table_name', $conditions);

if ($deleteSuccess) {
    echo "Data deleted successfully.";
} else {
    echo "Failed to delete data.";
}
```

#### Select Data:
The function ```selectData``` deletes records from the specified table.

##### Usage Example for array result:
```php
// Select data with conditions
$conditions = [
    'column1' => 'value1',
    'column2' => 42,  // Mixed data types
];

$data = selectData('your_table_name', $conditions, true);

if ($data) {
    foreach ($data as $row){
        echo $row['column1'] . "<br>";
        echo $row['column2'] . "<br>";
    }
} else {
    echo "No data found.";
}
```

##### Usage Example for object result:
```php
// Select data with conditions
$conditions = [
    'column1' => 'value1',
    'column2' => 42,  // Mixed data types
];

$data = selectData('your_table_name', $conditions);

if ($data) {
    foreach ($data as $row){
        echo $row->column1 . "<br>";
        echo $row->column2 . "<br>";
    }
} else {
    echo "No data found.";
}
```

##### Usage Example using join:
```php
// Select data with conditions
$conditions = [
    'column1' => 'value1',
    'column2' => 42,  // Mixed data types
];
$join = 'INNER JOIN your_table_name2 ON column1=your_table_name2.column';
$data = selectData('your_table_name', $conditions, false, $join);

if ($data) {
    foreach ($data as $row){
        echo $row->column1 . "<br>";
        echo $row->column2 . "<br>";
         echo $row->column;
    }
} else {
    echo "No data found.";
}
```

#### Usage Example with various WHERE operands

```php
$conditions = [
    'status:or' => 'Active',
    'status:or' => 'Approved',
    'age>' => 30,
    'score>=' => 50,
    'type!=' => 'guest',
    'department' => ['Sales', 'Marketing', 'HR'] // value in array for IN operator
];
```

## Parse Raw MySQLi Statement 
It will automatically convert your raw sql statement into a secure statement and then execute it for result.

### Usage Examples:

#### Insert Data

```php
$rawSql = "INSERT INTO your_table_name (column1, column2) VALUES ('" . $_POST['name'] . "', '" . $_POST['email'] . "')";
$insertSuccess = parseRawQuery($rawSql);

if ($insertSuccess) {
    echo "Data inserted successfully.";
} else {
    echo "Failed to insert data.";
}
```

#### Update Data

```php
$rawSql = "UPDATE your_table_name SET column1 = '" . $_POST['name'] . "', column2 = '" . $_POST['email'] . "' WHERE id = " . $_POST['id'];
$updateSuccess = parseRawQuery($rawSql);

if ($updateSuccess) {
    echo "Data updated successfully.";
} else {
    echo "Failed to update data.";
}
```

#### Delete Data

```php
$rawSql = "DELETE FROM your_table_name WHERE id = " . $_POST['id'];
$deleteSuccess = parseRawQuery($rawSql);

if ($deleteSuccess) {
    echo "Data deleted successfully.";
} else {
    echo "Failed to delete data.";
}
```

#### Select Data

```php
$rawSql = "SELECT * FROM your_table_name ORDER BY column1 DESC";
$rows = parseAndExecuteRawQuery($rawSql);

if ($rows) {
    print_r($rows); // Output the rows
} else {
    echo "No data found.";
}
```

## Othe Features

#### Get Maximum Value

```php
// Usage example
$table = 'your_table_name';
$column = 'your_column_name';

$maxValue = getMaxValue($table, $column);

if ($maxValue !== null) {
    echo "Maximum value in $column: " . $maxValue;
} else {
    echo "Failed to get maximum value.";
}
```

#### Get Minimum Value 

```php
// Usage example
$table = 'your_table_name';
$column = 'your_column_name';

$minValue = getMinValue($table, $column);

if ($minValue !== null) {
    echo "Minimum value in $column: " . $minValue;
} else {
    echo "Failed to get minimum value.";
}
```

#### Get Last Inserted Id
```php
// Usage example
$data = [
    'column1' => 'value1',
    'column2' => 'value2',
];

$insertSuccess = insertData('your_table_name', $data);

if ($insertSuccess) {
    $lastInsertId = getLastInsertedId();
    echo "Data inserted successfully. Last Inserted ID: " . $lastInsertId;
} else {
    echo "Failed to insert data.";
}
```

#### Execute Multiple MySQLi Query Statements

```php
// Example usage
$queries = [
    "INSERT INTO your_table_name (column1, column2) VALUES ('value1', 'value2')",
    "UPDATE your_table_name SET column1 = 'new_value1' WHERE column2 = 'value2'"
];

try {
    $result = executeTransaction($queries);
    echo "Transaction completed successfully.";
} catch (Exception $e) {
    echo "Transaction failed: " . $e->getMessage();
}
```

## MySQLiDatabase Class Use

### Example Usage:

#### 1. Create a Database Connection

Before using any methods, you need to get an instance of the MyDatabase class by passing the required database connection credentials (host, user, password, and database name).

```php 
// Include the MySQLiDatabase class
require_once 'MySQLiDatabase.class.php';

// Get the database instance (singleton pattern)
$db = MySQLiDatabase::getInstance('localhost', 'root', 'password', 'my_database');
```

#### 2. Insert Data into a Table

To insert data into a table, use the insertData method. For example, let's insert a new user into a users table.

```php
// Data to insert
$data = [
    'name' => 'John Doe',
    'email' => 'john.doe@example.com',
    'age' => 30
];

// Insert data
$lastInsertId = $db->insertData('users', $data);

// Check if the insert was successful
if ($lastInsertId) {
    echo "User inserted with ID: " . $lastInsertId;
} else {
    echo "Failed to insert user.";
}
```

#### 3. Select Data from a Table

To select data from a table, you can use the selectData method. For example, let's retrieve all users from the users table.

```php 
// Select all users
$users = $db->selectData('users');

// Print all users
foreach ($users as $user) {
    echo $user->name . " - " . $user->email . "<br>";
}
```

If you need to use conditions, such as fetching a user by their id:

```php
// Select a user where the ID is 1
$user = $db->getRow('users', ['id' => 1]);

if ($user) {
    echo "User: " . $user->name;
} else {
    echo "User not found.";
}
```

#### 4. Update Data in a Table

To update data in a table, use the updateData method. For example, let's update a user's email where their id is 1.

```php
// Data to update
$data = ['email' => 'new.email@example.com'];

// Conditions for update (where id = 1)
$conditions = ['id' => 1];

// Update user
$updated = $db->updateData('users', $data, $conditions);

if ($updated) {
    echo "User email updated successfully.";
} else {
    echo "Failed to update user.";
}
```

#### 5. Delete Data from a Table

To delete data from a table, use the deleteData method. For example, let's delete a user where id is 1.

```php
// Conditions for deletion (where id = 1)
$conditions = ['id' => 1];

// Delete user
$deleted = $db->deleteData('users', $conditions);

if ($deleted) {
    echo "User deleted successfully.";
} else {
    echo "Failed to delete user.";
}
```

#### 6. Handle Transactions

You can also execute multiple queries as a transaction. For example, let's perform an insert and update within a transaction.

```php
$queries = [
    "INSERT INTO users (name, email, age) VALUES ('Jane Doe', 'jane.doe@example.com', 25)",
    "UPDATE users SET age = 26 WHERE name = 'Jane Doe'"
];

try {
    $db->executeTransaction($queries);
    echo "Transaction completed successfully.";
} catch (Exception $e) {
    echo "Transaction failed: " . $e->getMessage();
}
```

#### 7. Executing Raw SQL Queries

If you want to run a raw SQL query (like SELECT, INSERT, UPDATE, or DELETE), you can use the parseRawQuery method. Here's how to use it:

```php
// Raw SQL query to select users
$sql = "SELECT * FROM users WHERE age > 20";

// Parse and execute the query
$users = $db->parseRawQuery($sql);

// Display results
foreach ($users as $user) {
    echo $user->name . " - " . $user->email . "<br>";
}
```

#### Summary of Methods:

    -- getInstance($dbhost, $dbuser, $dbpass, $dbname): Get the Singleton instance of the database connection.
    -- insertData($table, $data): Insert data into a table.
    -- selectData($table, $conditions, $asArray, $join, $columns): Select data from a table with optional conditions, join, and columns.
    -- updateData($table, $data, $conditions): Update data in a table with conditions.
    -- deleteData($table, $conditions): Delete data from a table with conditions.
    -- executeTransaction($queries): Execute multiple queries as part of a single transaction.
    -- getRow($table, $conditions): Get a single row based on conditions.
    -- getRows($table, $conditions): Get multiple rows based on conditions.
    -- parseRawQuery($rawSql): Execute a raw SQL query (e.g., SELECT, INSERT, UPDATE, DELETE).
