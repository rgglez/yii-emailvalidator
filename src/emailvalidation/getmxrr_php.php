<?php
/*
 * getmxrr.php
 *
 * @(#) $Header: /home/mlemos/cvsroot/PHPlibrary/getmxrr.php,v 1.1 2002/06/21 05:33:42 mlemos Exp $
 *
 */

/* ------------------------------------------------------------------------

   PHPresolver - PHP DNS resolver library
                 Version 1.1

   Copyright (c) 2001, 2002 Moriyoshi Koizumi <koizumi@ave.sytes.net>
   All Rights Reserved.

   This library is free software; you can redistribute it and/or modify it
   under the terms of the GNU Lesser General Public License as published
   by the Free Software Foundation; either version 2.1 of the License, or any
   later version.

   This library is distributed in the hope that it will be useful, but
   WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
   or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Lesser General Public

   License for more details.

   You should have received a copy of the GNU Lesser General Public License
   along with this library; if not,
   write to the Free Software Foundation, Inc.,
   59 Temple Place, Suite 330, Boston, MA 02111-1307  USA

  ------------------------------------------------------------------------ */

/***************************************************************************
 Description

  $_NAMESERVER[]

    The array that contains IP addresses or domain names of name servers
    used for DNS resolution.
    If nothing is set before require()'ing this library, the values will
    automatically prepared.

  bool getmxrr( string $hostname, arrayref $mxhosts, arrayref $weight );

    This function works in the same way as getmxrr(), however the
    third parameter cannot be omitted. If you need no MX preference
    information, please do like:


           getmxrr( 'example.com', $mxhosts, ${''} );

 -------------------------------------------------------------------------
 Configuration

  If you are doing with win32 environments and don't set $_NAMESERVER
  manually, make sure that ipconfig.exe is within the PATH.
  ipconfig.exe is generally distributed with any Microsoft(R) Windows
  distributions except for Windows 95.

 ***************************************************************************/

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'DNS.php');

/* rewrite this path to the same as the box's configuration
   if you run scripts on *NIX platforms */
define('RESOLV_CONF_PATH', '/etc/resolv.conf');

class getmxrr_php
{
   private $_NAMESERVERS = array();
   private $__PHPRESOLVER_RS;

   public function __construct($servers=null)
   {
      $this->_NAMESERVERS = $servers;
      if (isset($this->_NAMESERVERS) && (is_array($this->_NAMESERVERS) || count($this->_NAMESERVERS)==0))
         unset($this->_NAMESERVERS);

      if (!isset($this->_NAMESERVERS)) {
         $this->_NAMESERVERS = array();
         if (strncmp(PHP_OS, "WIN", 3) == 0 ) {
            unset( $res );
            exec('ipconfig /all', $res);
            $cnt = count($res);
            for ($i=0; $i<$cnt; ++$i) {
               if (strpos($res[$i], 'DNS Servers') !== false) {
                  $this->_NAMESERVERS[] = substr($res[$i], strpos($res[$i], ': ') + 2);
                  break;
               }
            }
            while ($i<$cnt-1 && strpos($res[++$i], ':') === false) {
               $this->_NAMESERVERS[] = trim($res[$i]);
            }
         }
         elseif (file_exists(RESOLV_CONF_PATH)) {
            $lines = file(RESOLV_CONF_PATH);
            $cnt = count($lines);
            for ($i=0; $i < $cnt; ++$i) {
               list($dr, $val) = split('[ \t]', $lines[$i]);
               if ($dr == 'nameserver') {
                  $this->_NAMESERVERS[] = rtrim($val);
               }
            }
            unset( $lines );
      	}
      }

      if (count($this->_NAMESERVERS))
         $this->__PHPRESOLVER_RS = new DNSResolver( $this->_NAMESERVERS[0] );
      else {
         unset($this->_NAMESERVERS);
         unset($this->__PHPRESOLVER_RS);
      }
   }

   public function GetMXRR($hostname, &$mxhosts, &$weight)
   {
      if (!isset($this->__PHPRESOLVER_RS))
         return(false);
      $dnsname = &DNSName::newFromString($hostname);
      $answer = & $__PHPRESOLVER_RS->sendQuery(new DNSQuery(new DNSRecord($dnsname, DNS_RECORDTYPE_MX)));
      if ($answer === false || $answer->rec_answer === false) {
         return false;
      }
      else {
         $i = count($answer->rec_answer);
         $mxhosts = $weight = array();
         while (--$i >= 0) {
   			if ($answer->rec_answer[$i]->type == DNS_RECORDTYPE_MX) {
               $rec = &$answer->rec_answer[$i]->specific_fields;
               $mxhosts[] = substr($rec['exchange']->getCanonicalName(), 0, -1);
               $weight[] = $rec['preference'];
   			}
         }
         return true;
      }
   }
}