<?php

namespace StoreKeeper\StoreKeeper\Helper\Api;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\StoreManagerInterface;
use Parsedown;

/**
 * @depracated
 */
class Categories extends AbstractHelper
{
    private Auth $authHelper;
    private CategoryFactory $categoryFactory;
    private CategoryRepository $categoryRepository;
    private CollectionFactory $categoryCollectionFactory;
    private StoreManagerInterface $storeManager;

    /**
     * Constructor
     *
     * @param Auth $authHelper
     * @param CategoryFactory $categoryFactory
     * @param CategoryRepository $categoryRepository
     * @param CollectionFactory $categoryCollectionFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Auth $authHelper,
        CategoryFactory $categoryFactory,
        CategoryRepository $categoryRepository,
        CollectionFactory $categoryCollectionFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->authHelper = $authHelper;
        $this->categoryFactory = $categoryFactory;
        $this->categoryRepository = $categoryRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * Get language for Store
     *
     * @param $storeId
     * @return mixed|string
     */
    public function getLanguageForStore($storeId)
    {
        return $this->authHelper->getLanguageForStore($storeId);
    }

    /**
     * Get list translated category for hooks
     *
     * @param $storeId
     * @param $language
     * @param int $offset
     * @param int $limit
     * @param array $order
     * @param array $filters
     * @return mixed
     * @throws \Exception
     */
    public function listTranslatedCategoryForHooks(
        $storeId,
        $language,
        int $offset,
        int $limit,
        array $order,
        array $filters
    ) {
        return $this->authHelper->getModule('ShopModule', $storeId)->listTranslatedCategoryForHooks(
            $language,
            $offset,
            $limit,
            $order,
            $filters
        );
    }

    /**
     * Update Category by Id
     *
     * @param $storeId
     * @param $storeKeeperId
     * @return void
     * @throws \Exception
     */
    public function updateById($storeId, $storeKeeperId)
    {
        $language = $this->authHelper->getLanguageForStore($storeId);

        $results = $this->authHelper->getModule('ShopModule', $storeId)->listTranslatedCategoryForHooks(
            $language,
            0,
            1,
            [
                [
                    'name' => 'category_type/id',
                    'dir' => 'asc'
                ]
            ],
            [
                [
                    "name" => "id__=",
                    'val' => (string) $storeKeeperId
                ]
            ]
        );

        if (isset($results['data']) && count($results['data']) > 0) {
            $result = $results['data'][0];
            if ($category = $this->exists($storeId, $result)) {
                $this->update($storeId, $category, $result);
            } else {
                $this->create($storeId, $result);
            }
        } else {
            throw new \Exception("Category {$storeKeeperId} does not exist in StoreKeeper");
        }
    }

    /**
     * Check if category Exist
     *
     * @param $storeId
     * @param array $result
     * @return false|\Magento\Framework\DataObject
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function exists($storeId, array $result)
    {
        $storekeeper_id = $this->getResultStoreKeeperId($result);
        $collection = $this->categoryCollectionFactory->create();
        $collection
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('storekeeper_category_id', $storekeeper_id)
            ->setFlag('has_stock_status_filter', false);

        if ($collection->count() > 0) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * Check if parent category Exist
     *
     * @param $storeId
     * @param array $result
     * @return false|\Magento\Framework\DataObject
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function parentExists($storeId, array $result)
    {
        if ($storekeeper_parent_id = $this->getResultParentId($result)) {
            $collection = $this->categoryCollectionFactory->create();
            $collection
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('storekeeper_category_id', $storekeeper_parent_id)
                ->setFlag('has_stock_status_filter', false);

            if ($collection->count() > 0) {
                return $collection->getFirstItem();
            }
        }

        return false;
    }

    /**
     * Create category
     *
     * @param $storeId
     * @param array $result
     * @return null
     */
    public function create($storeId, array $result)
    {
        return $this->update($storeId, null, $result);
    }

    /**
     * On deleted
     *
     * @param $storeId
     * @param $targetId
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function onDeleted($storeId, $targetId)
    {
        if ($target = $this->exists($storeId, [
            'id' => $targetId
        ])) {
            $this->storeManager->setCurrentStore($storeId);
            $target->setStoreId($storeId);
            $target->setIsActive(false);
            $this->categoryRepository->save($target);
        } else {
            $this->updateById($storeId, $targetId);
        }
    }

    /**
     * On created
     *
     * @param $storeId
     * @param $targetId
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function onCreated($storeId, $targetId)
    {
        if ($target = $this->exists($storeId, [
            'id' => $targetId
        ])) {
            $this->storeManager->setCurrentStore($storeId);
            $target->setStoreId($storeId);
            $target->setIsActive(true);
            $this->categoryRepository->save($target);
        } else {
            $this->updateById($storeId, $targetId);
        }
    }

    /**
     * Update category
     *
     * @param $storeId
     * @param $target
     * @param array $result
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function update($storeId, $target = null, array $result = [])
    {
        $language = $this->authHelper->getLanguageForStore($storeId);
        $shouldUpdate = false;
        $storekeeper_id = $this->getResultStoreKeeperId($result);
        $update = !is_null($target);
        $create = !$update;
        if ($update) {
            $target = $this->categoryFactory->create()->load($target->getId());
        } else {
            $shouldUpdate = true;
            $target = $this->categoryFactory->create();
        }

        $title = $result['title'] ?? null;
        $slug = $result['slug'] ?? null;
        $description = '';

        if (isset($result['description'])) {
            $description = $result['description'];
        }

        if (isset($result['translation'])) {
            if (isset($result['translation']['title'])) {
                $title = $result['translation']['title'];
            }
            if (isset($result['translation']['description'])) {
                $description = $result['translation']['description'];
            }
        }

        $target->setStoreId($storeId);
        $this->storeManager->setCurrentStore($storeId);

        if ($target->getName() != $title) {
            $shouldUpdate = true;
            $target->setName($title);
        }

        $parseDown = new Parsedown();
        $newDescription = $parseDown->text($description);

        $newDescription = <<<HTML
<style>
  #html-body [data-pb-style=H1A4J0C] {
    justify-content:flex-start;
    display:flex;
    flex-direction:column;
    background-position:left top;
    background-size:cover;
    background-repeat:no-repeat;
    background-attachment:scroll
  }
</style>
<div data-content-type="row" data-appearance="contained" data-element="main">
  <div data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}" data-background-type="image" data-video-loop="true" data-video-play-only-visible="true" data-video-lazy-load="true" data-video-fallback-src="" data-element="inner" data-pb-style="H1A4J0C">
    <div data-content-type="text" data-appearance="default" data-element="main">
      $newDescription
    </div>
  </div>
</div>
HTML;

        if ($target->getDescription() != $newDescription) {
            $shouldUpdate = true;
            $target->setDescription($newDescription);
        }

        $published = $result['published'] ?? false;
        $isActive = $target->getIsActive();

        if (is_null($isActive)) {
            $target->setIsActive($published);
        } elseif ($isActive && !$published) {
            $shouldUpdate = true;
            $target->setIsActive(false);
        } elseif (!$isActive && $published) {
            $shouldUpdate = true;
            $target->setIsActive(true);
        }

        $storeKeeperCategoryIdAttribute = $target->getCustomAttribute('storekeeper_category_id');

        if (
            empty($storeKeeperCategoryIdAttribute) ||
            $storeKeeperCategoryIdAttribute->getValue() != $storekeeper_id
        ) {
            $shouldUpdate = true;
            $target->setCustomAttribute('storekeeper_category_id', $storekeeper_id);
        }

        $shouldMove = false;
        $parent = null;

        if ($parent = $this->parentExists($storeId, $result)) {
            if ($target->getParentId() != $parent->getId()) {
                $shouldUpdate = true;
                $shouldMove = true;
            }
        }

        $seo_title = $result['seo_title'] ?? null;
        if ($target->getMetaTitle() != $seo_title) {
            $shouldUpdate = true;
            $target->setMetaTitle($seo_title);
        }

        $seo_description = $result['seo_description'] ?? null;
        if ($target->getMetaDescription() != $seo_description) {
            $shouldUpdate = true;
            $target->setMetaDescription($seo_description);
        }

        $seo_keywords = $result['seo_keywords'] ?? null;
        if ($target->getMetaKeywords() != $seo_keywords) {
            $shouldUpdate = true;
            $target->setMetaKeyswords($seo_keywords);
        }

        if ($shouldUpdate) {
            if ($shouldMove && $update) {
                // categories can only be moved if they exist
                if ($parent) {
                    $target->move($parent->getId(), null);
                }
            }

            $target = $this->categoryRepository->save($target);

            if ($shouldMove && $create) {
                // categories can only be moved if they exist

                if ($parent) {
                    $target->move($parent->getId(), null);
                    $target = $this->categoryRepository->save($target);
                }
            }

            if ($update && $language == ' ') {
                $this->setCategoryToUseDefaultValues($target, $storeId);
            }
        }
    }

    /**
     * Get result StoreKeeperId
     *
     * @param array $result
     * @return mixed
     */
    private function getResultStoreKeeperId(array $result)
    {
        return $result['id'];
    }

    /**
     * Get result parent Id
     *
     * @param array $result
     * @return false|mixed
     */
    private function getResultParentId(array $result)
    {
        return $result['parent_id'] ?? false;
    }

    /**
     * Set category to use Default Values
     *
     * @param $target
     * @param $storeId
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    private function setCategoryToUseDefaultValues($target, $storeId)
    {
        $this->storeManager->setCurrentStore(\Magento\Store\Model\Store::DEFAULT_STORE_ID);
        $target->setStoreId($storeId);
        $productData = $target->getData();
        $productData['name'] = null;
        $productData['description'] = false;

        $target->setData($productData);
        $target->save();
        $this->categoryRepository->save($target);
    }
}
