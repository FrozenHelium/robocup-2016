<?php

require_once 'Query.php';

class Model
{

    public function get_schema() {
        return null;
    }

    public static function get_table_name() {
        return to_snake_case(get_called_class());
    }

    public static function exists() {
        $db = Database::get_instance();
        $res = $db->query_with_error("SHOW TABLES LIKE '" .
            self::get_table_name() . "'");
        return $res->num_rows > 0;
    }

    public function get_create_table_sql() {
        $sql = "CREATE TABLE IF NOT EXISTS " . $this->get_table_name() . " (";

        $primary_key = "`id`";
        $sql .= " $primary_key INT(11) NOT NULL AUTO_INCREMENT";

        $schema = $this->get_schema();
        $suffix = "";

        // Use schema to deduce field names and types.
        if ($schema) {
            foreach ($schema as $item) {
                $name = $item[0];
                $type = self::get_sql_type($item);

                if ($type && $name) {
                    $sql .= ", `$name` $type NOT NULL";
                }

                $extra = get_item($item, "extra");
                if ($extra) {
                    $sql .= " $extra";
                }

                $unique = get_item($item, "unique");
                if ($unique) {
                    $suffix .= ", UNIQUE KEY `$name` (`$name`)";
                }
            }
        }

        $sql .= ", PRIMARY KEY (`id`)$suffix)";

        return $sql;
        # Database::get_instance()->query_with_error($sql);
    }

    public function get_schema_fields() {
        $schema = $this->get_schema();
        if ($schema) {
            $fields = array('id');
            $types = array('id'=>'integer');
            foreach ($schema as $item) {
                $fields[] = $item[0];
                $types[$item[0]] = $item[1];
            }
            return array($fields, $types);
        }
        return array(null, null);
    }

    public function presave() {}
    public function postsave() {}

    public function save() {
        $this->presave();
        $keys = "";
        $values = "";
        $update_string = "";

        $reflect = new ReflectionObject($this);
        $props = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);
        list($fields, $types) = $this->get_schema_fields();
        if (!$fields || !$types)
            return;

        $db = Database::get_instance();

        foreach ($props as $prop) {
            $name = $prop->getName();
            if (!in_array($name, $fields))
                continue;

            $val = $prop->getValue($this);

            if ($types[$name] == "datetime") {
                $time = $val->format("Y-m-d H:i:s");
                $val = "'$time'";
            }
            else {
                $val = $db->real_escape_string($val);
                $val = "'$val'";
            }

            if ($keys!="")
                $keys .= ",";
            $keys .= "$name";

            if ($values!="")
                $values .= ",";
            $values .= "$val";

            if ($update_string!="")
                $update_string .= ",";
            $update_string .= "$name=$val";
        }

        $sql = "INSERT INTO " . $this->get_table_name() .
            " ($keys) VALUES($values) " .
            " ON DUPLICATE KEY UPDATE $update_string";

        if (!isset($this->id)) {
            $this->id = $db->insert_id;
        }

        $db->query_with_error($sql);
        $this->postsave();
    }

    public function delete() {
        if (!isset($this->id))
            return;

        $sql = "DELETE FROM " . $this->get_table_name() .
            " WHERE id=" . $this->id;

        $db = Database::get_instance();
        $db->query_with_error($sql);
    }

    public static function delete_all() {
        $db = Database::get_instance();
        $table = self::get_table_name();
        $sql = "DELETE FROM $table";
        $db->query_with_error($sql);
    }

    public static function get_from_query_result($result) {
        $objects = array();
        if ($result->num_rows > 0) {
            $i = 0;
            while ($row = $result->fetch_assoc()) {
                $field_info = $result->fetch_field_direct($i++);

                $class = get_called_class();
                $obj = new $class();
                list($fields, $types) = $obj->get_schema_fields();

                foreach ($obj as $key=>$val) {
                    unset($obj->$key);
                }

                foreach ($row as $key=>$val) {
                    if ($types[$key] == "datetime")
                        $obj->$key = new DateTime($val);
                    else
                        $obj->$key = $val;
                }

                $objects[] = $obj;
            }
        }
        return $objects;
    }

    public static function get_all() {
        $db = Database::get_instance();
        $table = self::get_table_name();
        $sql = "SELECT * FROM $table";
        $result = $db->query_with_error($sql);
        return self::get_from_query_result($result);
    }

    public static function raw_query($sql) {
        $db = Database::get_instance();
        $result = $db->query_with_error($sql);
        return self::get_from_query_result($result);
    }

    public static function get_db() {
        return Database::get_instance();
    }

    public static function query() {
        return new Query(get_called_class());
    }

    public static function get_sql_type($item) {
        $type = $item[1];
        $max_length = get_item($item, "max_length");

        $result = "";
        switch (strtolower($type)) {
            case 'integer':
                if (!$max_length)
                    $max_length = 11;
                $result = "INT($max_length)";
                break;
            case 'string':
                if (!$max_length)
                    $max_length = 30;
                $result = "VARCHAR($max_length)";
                break;
            case 'text':
                $result = "TEXT";
                break;
            case 'datetime':
                $result = "DATETIME";
                break;
            case 'boolean':
                $result = "BOOL";
                break;
            default:
                return null;
        }

        $default = get_item($item, "default");
        if ($default) {
            $result .= " DEFAULT $default";
        }
        return $result;
    }
}

?>
