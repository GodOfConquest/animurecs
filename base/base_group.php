<?php

class BaseGroup implements Iterator, ArrayAccess {
  // class to provide mass-querying functions for groups of object IDs or objects.
  // you can treat this as if it were an array of objects
  // e.g. foreach($group->load('info') as $object) or $group->load('info')[1]

  public static $URL;

  protected $_objects,$_objectGroups,$_objectKeys = [];
  private $position = 0;
  protected $_tagCounts=Null;
  protected $_pulledInfo=False;
  public $intKeys=True;
  public $dbConn,$app=Null;
  protected $_groupTable,$_groupTableSingular,$_groupObject,$_nameField = Null;

  public function __construct(Application $app, array $objects) {
    // preserves keys of input array.
    $this->position = 0;
    $this->app = $app;
    $this->_objects = [];
    if (count($objects) > 0) {
      foreach ($objects as $key=>$object) {
        $this->intKeys = $this->intKeys && is_int($key);
      }
      if (current($objects) instanceof $this->_groupObject) {
        $this->_objects = $objects;
      } elseif (is_numeric(current($objects))) {
        foreach ($objects as $key=>$objectID) {
          $this->_objects[$key] = new $this->_groupObject($this->app, intval($objectID));
        }
      }
    }
    $this->_objectKeys = array_keys($this->_objects);
    $this->_setObjectGroups();
  }
  // iterator functions.
  public function rewind() {
    $this->position = 0;
  }
  public function current() {
    return $this->objects()[$this->_objectKeys[$this->position]];
  }
  public function key() {
    return $this->position;
  }
  public function next() {
    ++$this->position;
  }
  public function valid() {
    return isset($this->_objectKeys[$this->position]) && isset($this->objects()[$this->_objectKeys[$this->position]]);
  }

  // array access functions.
  public function offsetSet($offset, $value) {
    if (is_null($offset)) {
      $this->objects()[] = $value;
    } else {
      $this->objects()[$offset] = $value;
    }
  }
  public function offsetExists($offset) {
    return isset($this->objects()[$offset]);
  }
  public function offsetUnset($offset) {
    unset($this->objects()[$offset]);
  }
  public function offsetGet($offset) {
    return isset($this->objects()[$offset]) ? $this->objects()[$offset] : null;
  }

  // we need to set object groups by object table, so we can query the proper tables for information when eager-loading is triggered.
  protected function _setObjectGroups() {
    $this->_objectGroups = [];
    foreach ($this->_objects as $key=>$object) {
      $objectClass = get_class($object);
      if (!isset($this->_objectGroups[$objectClass::$TABLE])) {
        $this->_objectGroups[$objectClass::$TABLE] = [$key=>$object];
      } else {
        $this->_objectGroups[$objectClass::$TABLE][$key] = $object;
      }
    }
  }

  // returns the string name of the database table for the first object in this group.
  public function groupTable() {
    if ($this->_groupTable === Null) {
      if ($this->_objects) {
        $objectClass = get_class(current($this->_objects));
        $this->_groupTable = $objectClass::$TABLE;
      }
    }
    return $this->_groupTable;
  }

  // returns the string name of the first object in this group.
  public function groupObject() {
    if ($this->_groupObject === Null) {
      if ($this->_objects) {
        $objectClass = get_class(current($this->_objects));
        $this->_groupObject = $objectClass::MODEL_NAME();
      }
    }
    return $this->_groupObject;
  }
  public function objects() {
    return $this->_objects;
  }
  public function load($attrs) {
    // eager-loads properties or methods, returning the current object.
    // input can be a single string (name of property/method of element)
    // e.g. 'info' or 'tags'

    // or array('attr' => 'attr') (name of property/methods of object group belonging to element)
    // e.g. array('tags' => 'info')

    if (!is_array($attrs)) {
      if (method_exists($this, $attrs)) {
        $this->$attrs();
      } elseif (property_exists($this, $attrs)) {
        $this->attrs;
      }
    } else {
      $key = key($attrs);
      if ($key !== Null) {
        $value = $attrs[$key];
        if (method_exists($this, $key) && is_object($this->$key())) {
          if (method_exists($this->$key(), $value)) {
            $this->$key()->$value();
          } elseif (property_exists($this->$key(), $value)) {
            $this->$key()->$value;
          }
        } elseif (property_exists($this, $key) && is_object($this->$key)) {
          if (method_exists($this->$key, $value)) {
            $this->$key->$value();
          } elseif (property_exists($this->$key, $value)) {
            $this->$key->$value;
          }
        }
      }
    }
    return $this;
  }
  protected function _getInfo() {
    foreach ($this->_objectGroups as $groupTable=>$objectList) {
      $inclusion = [];
      $idToListIndex = [];
      foreach ($objectList as $key=>$object) {
        $inclusion[$key] = $object->id;
        $idToListIndex[$object->id] = $key;
      }
      if ($inclusion) {
        $objectName = get_class(current($objectList));
        $modelName = $objectName::MODEL_NAME();
        $cacheKeys = array_map(function($object) {
          return $object->cacheKey();
        }, $objectList);
        $casTokens = [];

        // fetch as many objects as we can from the cache.
        $cacheValues = $this->app->cache->get($cacheKeys, $casTokens);
        if ($cacheValues) {
          $objectsFound = [];
          foreach ($cacheValues as $cacheKey=>$cacheValue) {
            if ($cacheValue) {
              // lop off the Object- from the cache key to get the ID, so we can set the appropriate object's values.
              $objectID = intval(explode("-", $cacheKey)[1]);
              $objectList[$idToListIndex[$objectID]]->set($cacheValue);
              $objectsFound[] = $objectID;
            }
          }
          $inclusion = array_diff($inclusion, $objectsFound);
        }
        if ($inclusion) {
          // we have objects that are not yet cached. pull them from the db.
          $infoToCache = [];
          $objectInfo = $this->app->dbConn->table($groupTable)->where(['id' => $inclusion])->assoc();
          foreach ($objectInfo as $info) {
            $object = $objectList[$idToListIndex[intval($info['id'])]];
            $object->set($info);
            $infoToCache[$object->cacheKey()] = $info;
          }
          // now set these object values in the cache.
          foreach ($infoToCache as $cacheKey=>$cacheValue) {
            $this->app->cache->set($cacheKey, $cacheValue);
          }
        }
      }
    }
  }
  public function info() {
    if (!$this->_pulledInfo) {
      $this->_pulledInfo = True;
      $this->_getInfo();
    }
    return $this->_objects;
  }
  public function length() {
    return count($this->_objects);
  }
  protected function _getTagCounts() {
    $inclusion = [];
    foreach ($this->_objects as $object) {
      $inclusion[] = $object->id;
    }
    $tagCountList = $inclusion ? $this->app->dbConn->table($this->_groupTable."_tags")->fields('tag_id', 'COUNT(*)')->join('tags ON tags.id=tag_id')->where([$this->_groupTableSingular."_id" => $inclusion])->group('tag_id')->order('COUNT(*) DESC')->assoc('tag_id', 'COUNT(*)') : [];
    foreach ($tagCountList as $id=>$count) {
      $tagCountList[$id] = ['tag' => new Tag($this->app, intval($id)), 'count' => intval($count)];
    }
    return $tagCountList;
  }
  public function tagCounts() {
    if ($this->_tagCounts === Null) {
      $this->_tagCounts = $this->_getTagCounts();
    }
    return $this->_tagCounts;
  }
  public function append(BaseGroup $group, $override=False) {
    // appends another basegroup's objects to this one.
    // overrides keys if any non-numeric.
    foreach ($group->objects() as $key=>$object) {
      if (!$override && $this->intKeys && $group->intKeys) {
        array_push($this->_objects, $object);
      } else {
        $this->_objects[$key] = $object;
      }
    }
    $this->_objectKeys = array_keys($this->_objects);
    $this->_setObjectGroups();
    return $this->objects();
  }
  public function filter($filterFunction) {
    // filter the objects in this group by the given filterFunction and returns a new group.
    $className = get_class($this);
    $filteredObjects = array_filter($this->_objects, $filterFunction);
    return new $className($this->app, $filteredObjects ? $filteredObjects : []);
  }
  public function sort($sortFunction) {
    // sorts the objects in this group by the given sortFunction and returns a new group.
    $className = get_class($this);

    // since uasort works in-place, we have to make a copy of our list of objects.
    $sortedObjects = $this->_objects;
    @uasort($sortedObjects, $sortFunction);
    return new $className($this->app, $sortedObjects ? $sortedObjects : []);
  }
  public function limit($n) {
    // returns the first N objects in this group as a new group.
    $className = get_class($this);
    $limitedObjects = array_slice($this->_objects, 0, $n);
    return new $className($this->app, $limitedObjects ? $limitedObjects : []);
  }
  public function view($view="index", array $params=Null) {
    $file = joinPaths(Config::APP_ROOT, 'views', static::$URL, "$view.php");
    if (file_exists($file)) {
      ob_start();
      include($file);
      return ob_get_clean();
    }
    // Should never get here!
    throw new AppException($this->app, "Requested view not found: ".$file);
  }
}

?>