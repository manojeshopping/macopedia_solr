<?php

/**
 * Copyright (c) 2012, Macopedia
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *  - Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *  - Neither the name of the Macopedia nor the names of its contributors
 *    may be used to endorse or promote products derived from this software
 *    without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 * 
 * @copyright Copyright 2012, Macopedia (http://www.Macopedia.fr)
 * @license http://www.opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 * 
 * @category Solr
 * @package Macopedia_Solr
 * @author Matthieu Vion <contact@Macopedia.fr>
 */

class Macopedia_Solr_Model_Indexer {
    
    /**
    * Refresh Solr Index
    * 
    * @param Varien_Event_Observer $observer
    */
    public function refresh($observer) {
        if(!Mage::getStoreConfigFlag('solr/active/admin')) {
            return;
        }
        
        $products = $this->_getConnection()->fetchAll('SELECT * FROM '.$this->_getTable('catalogsearch/fulltext'));
        
        $documents = array();
        
        foreach($products as $product) {
            
            $magProduct = Mage::getModel('catalog/product')->load($product['product_id']);
            $prodCat = $magProduct->getCategoryIds();
            $magProductStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($magProduct);
            
            $document = Mage::getModel('solr/document');
            $document->id = $product['product_id'];
            $document->store_id = $product['store_id'];
            $document->fulltext = $product['data_index'];
            $document->sku = $magProduct->getSku();
            $document->status = $magProduct->getStatus();
            $document->name = $magProduct->getName();
            $document->description = $magProduct->getDescription();
            $document->short_description = $magProduct->getShortDescription();
            $document->price = $magProduct->getPrice();
            //$document->meta_title = $magProduct->getPrice(); todo
            //$document->meta_description = $magProduct->getPrice(); todo
            //$document->meta_keyword = $magProduct->getPrice();
            $document->image = $magProduct->getImageUrl();
            $document->small_image = $magProduct->getSmallImageUrl();
            $document->thumbnail = $magProduct->getThumbnailUrl();
            //$document->url_key = $magProduct->getPrice(); todo
            $document->url_path = $magProduct->getProductUrl();
            $document->qty = $magProductStock->getQty();
            $document->min_qty = $magProductStock->getMinQty();
            $document->is_in_stock = $magProductStock->getIsInStock();
            
            foreach ($prodCat as $category_id) {
                $_cat = Mage::getModel('catalog/category')->load($category_id) ;
                $document->setMultiValue('categories', $_cat->getName());
            } 
            
            $documents[] = $document;
            
            unset($magProduct);
            unset($magProductStock);
        }
        
        try {
            $search = Mage::getModel('solr/search');
            $search->deleteAllDocuments();
            $search->addDocuments($documents);
            $search->commit();
            $search->optimize();
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addNotice(Mage::helper('solr')->__('Can not index data in Solr. %s',$e->getMessage()));
        }

        return;
    }
    
    /**
    * Retrieve resource
    * 
    * @return Mage_Core_Model_Resource
    */
    public function _getResource() {
        return Mage::getSingleton('core/resource');
    }
    
    /**
    * Retrieve connection
    * 
    * @return Varien_Db_Adapter_Pdo_Mysql
    */
    public function _getConnection() {
        return $this->_getResource()->getConnection('core_read');
    }
    
    /**
    * Retrieve table name
    * 
    * @return string
    */
    public function _getTable($tableName){
        return $this->_getResource()->getTableName($tableName);
    }
    
}