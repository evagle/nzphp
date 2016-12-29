<?php
/**
 * Created by PhpStorm.
 * User: abing
 * Date: 27/12/2016
 * Time: 17:13
 */

namespace ZPHP\Cache;


class SimpleLRUCache
{
    private $head;
    private $tail;
    private $capacity;
    private $hashmap;

    public function __construct($capacity)
    {
        $this->capacity = $capacity;
        $this->hashmap = array();
        $this->head = new Node(null, null);
        $this->tail = new Node(null, null);
        $this->head->setNext($this->tail);
        $this->tail->setPrevious($this->head);
    }

    public function get($key)
    {
        if (!isset($this->hashmap[$key])) {
            return null;
        }
        $node = $this->hashmap[$key];
        if (count($this->hashmap) == 1) {
            return $node->getData();
        }

        $this->detachNode($node);
        $this->attachNode($this->head, $node);
        return $node->getData();
    }

    public function put($key, $data)
    {
        if ($this->capacity <= 0) {
            return false;
        }
        if (isset($this->hashmap[$key]) && !empty($this->hashmap[$key])) {
            $node = $this->hashmap[$key];
            $this->detachNode($node);
            $this->attachNode($this->head, $node);
            $node->setData($data);
        } else {
            $node = new Node($key, $data);
            $this->hashmap[$key] = $node;
            $this->attachNode($this->head, $node);
            if (count($this->hashmap) > $this->capacity) {
                $nodeToRemove = $this->tail->getPrevious();
                $this->detachNode($nodeToRemove);
                unset($this->hashmap[$nodeToRemove->getKey()]);
            }
        }
        return true;
    }

    public function remove($key)
    {
        if (!isset($this->hashmap[$key])) {
            return false;
        }
        $nodeToRemove = $this->hashmap[$key];
        $this->detachNode($nodeToRemove);
        unset($this->hashmap[$nodeToRemove->getKey()]);
        return true;
    }

    public function flush()
    {
        $node = $this->head->getNext();
        while ($node->getKey()) {
            $this->detachNode($node);
            $node = $this->head->getNext();
        }
        $this->hashmap = array();
    }

    private function attachNode(Node $head, Node $node)
    {
        $node->setPrevious($head);
        $node->setNext($head->getNext());
        $node->getNext()->setPrevious($node);
        $node->getPrevious()->setNext($node);
    }

    private function detachNode(Node $node)
    {
        $node->getPrevious()->setNext($node->getNext());
        $node->getNext()->setPrevious($node->getPrevious());
    }
}

class Node
{
    private $key;
    private $data;
    private $next;
    private $previous;

    public function __construct($key, $data)
    {
        $this->key = $key;
        $this->data = $data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function setNext($next)
    {
        $this->next = $next;
    }

    public function setPrevious($previous)
    {
        $this->previous = $previous;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * @return Node
     */
    public function getNext()
    {
        return $this->next;
    }

    /**
     * @return Node
     */
    public function getPrevious()
    {
        return $this->previous;
    }
}