
<?php
class LDAPQuery 
{

  public $attributes = array('homeDirectory', 'givenName', 'sn', 'ou', 'labeledURI', 'mail', 'eduPersonPrimaryAffiliation', 'uvmEduAffiliation');

  public $ldap_uri = 'ldaps://ldap.uvm.edu';
  public $ldap_dn_string = 'dc=uvm,dc=edu';

  public function get_entries($query)
  { 
    $entry = $this->flatten_ldap_arrays($this->directory_query($query));
    //$attributes = json_decode($this->attributes);
    $attributes = $this->attributes;
    foreach( $attributes as $attribute ) {
      if (array_key_exists($attribute, $entry)) {
        $filtered_entry[$attribute] = $entry[$attribute];
      }
    }

    return $filtered_entry;
  }


  public function flatten_ldap_arrays($ldap_array)
  {
    foreach ($ldap_array as &$item) {
      if (is_array($item)) {
        $item = implode(";", $item);
      }
    }
    unset($item);
    return $ldap_array;
  }



  public function directory_query($prefix, $query)
  {
    //$prefix = $this->ldap_filter_prefix;
    $filter = $prefix . "=" . $query;

    // Connection and Base DN string configuration.
    $ldapserver = $this->ldap_uri;
    $dnstring = $this->ldap_dn_string;

    $ds = ldap_connect($ldapserver);

    if ($ds) {
      // Bind (anonymously, no auth) and search
      $r = ldap_bind($ds);
      $sr = ldap_search($ds, $dnstring, $filter);

      // Kick out on a failed search.
      if (!ldap_count_entries($ds, $sr)) {
        return false;
      }

      // Retrieve records found by the search
      $info = ldap_get_entries($ds, $sr);
      $entry = ldap_first_entry($ds, $sr);
      $attrs = ldap_get_attributes($ds, $entry);

      // Close the door on the way out.
      ldap_close($ds);

      return $info;
      //return $this->cleanUpEntry($attrs);
    }
  }


  public function cleanUpEntry($entry)
  {
    $retEntry = array();
    for ($i = 0; $i < $entry['count']; $i++) {
      if (is_array($entry[$i])) {
        $subtree = $entry[$i];
        if (!empty($subtree['dn']) and !isset($retEntry[$subtree['dn']])) {
          $retEntry[$subtree['dn']] = $this->cleanUpEntry($subtree);
        } else {
          $retEntry[] = $this->cleanUpEntry($subtree);
        }
      } else {
        $attribute = $entry[$i];
        if ($entry[$attribute]['count'] == 1) {
          $retEntry[$attribute] = $entry[$attribute][0];
        } else {
          for ($j = 0; $j < $entry[$attribute]['count']; $j++) {
            $retEntry[$attribute][] = $entry[$attribute][$j];
          }
        }
      }
    }
    return $retEntry;
  }

}



