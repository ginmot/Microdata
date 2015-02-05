<?php
/**
 * @package     Microdata
 * @author      Axel Etcheverry <axel@etcheverry.biz>
 * @copyright   Copyright (c) 2011 Axel Etcheverry (http://www.axel-etcheverry.com)
 * Displays     <a href="http://creativecommons.org/licenses/MIT/deed.fr">MIT</a>
 * @license     http://creativecommons.org/licenses/MIT/deed.fr    MIT
 * 
 * Based on MicrodataPHP
 */

/**
 * @namespace
 */
namespace Microdata\Reader;

/**
 * Extend the DOMElement class with the Microdata API functions.
 */
class Element extends \DOMElement
{
    /**
     * Determine whether the itemscope attribute is present on this element.
     *
     * @return
     *   boolean TRUE if this is an item, FALSE if it is not.
     */
    public function itemScope()
    {
        return $this->hasAttribute('itemscope');
    }

    /**
     * Retrieve this item's itemtypes.
     *
     * @return
     *   An array of itemtype tokens.
     */
    public function itemType()
    {
        $itemtype = $this->getAttribute('itemtype');
        if (!empty($itemtype)) {
            return $this->_tokenList($itemtype);
        }
        // Return NULL instead of the empty string returned by getAttributes so we
        // can use the function for boolean tests.
        return null;
    }

    /**
     * Retrieve this item's itemid.
     *
     * @return
     *   A string with the itemid.
     */
    public function itemId()
    {
        $itemid = $this->getAttribute('itemid');
        if (!empty($itemid)) {
            return $itemid;
        }
        // Return NULL instead of the empty string returned by getAttributes so we
        // can use the function for boolean tests.
        return null;
    }

    /**
     * Retrieve this item's itemprops.
     *
     * @return
     *   An array of itemprop tokens.
     */
    public function itemProp()
    {
        $itemprop = $this->getAttribute('itemprop');
        if (!empty($itemprop)) {
            return $this->_tokenList($itemprop);
        }
        return array();
    }

    /**
     * Retrieve the ids of other items which this item references.
     *
     * @return
     *   An array of ids as contained in the itemref attribute.
     */
    public function itemRef()
    {
        $itemref = $this->getAttribute('itemref');
        if (!empty($itemref)) {
            return $this->_tokenList($itemref);
        }
        return array();
    }

    /**
     * Retrieve the properties
     *
     * @return
     *   An array of Elements which are properties of this
     *   element.
     */
    public function properties()
    {
        $props = array();

        if ($this->itemScope()) {
            $toTraverse = array($this);

            foreach ($this->itemRef() as $itemref) {
                $children = $this->ownerDocument->xpath()->query('//*[@id="'.$itemref.'"]');
                foreach($children as $child) {
                    $this->_traverse($child, $toTraverse, $props, $this);
                }
            }
            
            while (count($toTraverse)) {
                $this->_traverse($toTraverse[0], $toTraverse, $props, $this);
            }
        }

        return $props;
    }

    /**
     * Retrieve the element's value, determined by the element type.
     *
     * @return
     *   The string value if the element is not an item, or $this if it is
     *   an item.
     */
    public function itemValue()
    {
        $itemprop = $this->itemProp();
        
        if (empty($itemprop)) {
            return null;
        }
        
        if ($this->itemScope()) {
            return $this;
        }
        
        switch (strtoupper($this->tagName)) {
            case 'META':
                return $this->getAttribute('content');
            case 'AUDIO':
            case 'EMBED':
            case 'IFRAME':
            case 'IMG':
            case 'SOURCE':
            case 'TRACK':
            case 'VIDEO':
                // @todo Should this test that the URL resolves?
                return $this->getAttribute('src');
            case 'OBJECT':
                // @todo Should this test that the URL resolves?
                return $this->getAttribute('data');
            case 'DATA':
                return $this->getAttribute('value');
            case 'TIME':
                $datetime = $this->getAttribute('datetime');
                if (!empty($datetime)) {
                    return $datetime;
                }
            default:
                return $this->textContent;
        }
    }

    /**
     * Parse space-separated tokens into an array.
     *
     * @param string $string
     *   A space-separated list of tokens.
     *
     * @return array
     *   An array of tokens.
     */
    protected function _tokenList($string)
    {
        return explode(' ', trim($string));
    }

    /**
     * Traverse the tree.
     * 
     * In MicrodataJS, this is handled using a closure.
     * See comment for MicrodataPhp:getObject() for an explanation of closure use
     * in this library.
     */
    protected function _traverse($node, &$toTraverse, &$props, $root)
    {
        foreach ($toTraverse as $i => $elem)  {
            if ($elem->isSameNode($node)){
                unset($toTraverse[$i]);
            }
        }
        if (!$root->isSameNode($node)) {
            $names = $node->itemProp();
            if (count($names)) {
                //@todo Add support for property name filtering.
                $props[] = $node;
            }
            if ($node->itemScope()) {
                return;
            }
        }
        if (isset($node)) {
            // An xpath expression is used to get children instead of childNodes
            // because childNodes contains DOMText children as well, which breaks on
            // the call to getAttributes() in itemProp().
            $children = $this->ownerDocument->xpath()->query($node->getNodePath() . '/*');
            foreach ($children as $child) {
                $this->_traverse($child, $toTraverse, $props, $root);
            }
        }
    }
}
