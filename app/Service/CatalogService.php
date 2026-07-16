<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AuthException;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;

/** 管理 API 商品、版本、端点、文档、定价及发布生命周期。 */
class CatalogService
{
    /** 初始化当前组件所需的依赖。 */
    public function __construct(private Db $db, private IdGeneratorInterface $ids) {}

    /** 创建 `createProduct` 方法对应的数据或业务状态。 @return array{product_id:int,version_id:int} */
    public function createProduct(int $supplierId, string $name, string $slug, string $summary): array
    {
        [$name, $slug, $summary] = $this->productFields($name, $slug, $summary);
        return $this->db->transaction(function (ConnectionInterface $c) use ($supplierId, $name, $slug, $summary): array {
            if ($supplierId <= 0 || $c->selectOne('SELECT `id` FROM `api_products` WHERE `supplier_profile_id` = ? AND `slug` = ? LIMIT 1 FOR UPDATE', [$supplierId, $slug]) !== null) { throw AuthException::conflict('API product slug already exists.'); }
            $productId = $this->ids->generate(); $versionId = $this->ids->generate();
            $c->insert('INSERT INTO `api_products` (`id`,`supplier_profile_id`,`name`,`slug`,`summary`,`status`,`current_published_version_id`,`created_at`,`updated_at`,`deleted_at`) VALUES (?,?,?,?,?,\'draft\',NULL,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,NULL)', [$productId, $supplierId, $name, $slug, $summary]);
            $c->insert('INSERT INTO `api_versions` (`id`,`api_product_id`,`version`,`name`,`summary`,`status`,`published_at`,`created_at`,`updated_at`,`deleted_at`) VALUES (?,?,\'v1\',?,?,\'draft\',NULL,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,NULL)', [$versionId, $productId, $name, $summary]);
            return ['product_id' => $productId, 'version_id' => $versionId];
        });
    }

    /** 执行 `saveDraft` 方法对应的业务处理。 @param list<array{method:string,path:string,name:string,description?:string}> $endpoints */
    public function saveDraft(int $supplierId, int $productId, int $versionId, string $name, string $summary, string $version, string $documentation, int $priceMicros, string $currency, array $endpoints): void
    {
        [$name, , $summary] = $this->productFields($name, 'valid-slug', $summary); $version = trim($version); $currency = strtoupper(trim($currency));
        if ($version === '' || strlen($version) > 32 || trim($documentation) === '' || strlen($documentation) > 1_000_000 || $priceMicros < 0 || preg_match('/^[A-Z]{3}$/D', $currency) !== 1 || $endpoints === [] || count($endpoints) > 100) { throw AuthException::badRequest('Draft content is incomplete.'); }
        $this->db->transaction(function (ConnectionInterface $c) use ($supplierId, $productId, $versionId, $name, $summary, $version, $documentation, $priceMicros, $currency, $endpoints): void {
            $draft = $c->selectOne('SELECT v.`id`,v.`status`,pr.`unit_price_micros` FROM `api_versions` v INNER JOIN `api_products` p ON p.`id`=v.`api_product_id` LEFT JOIN `api_prices` pr ON pr.`api_version_id`=v.`id` AND pr.`deleted_at` IS NULL WHERE v.`id`=? AND p.`id`=? AND p.`supplier_profile_id`=? AND v.`deleted_at` IS NULL AND p.`deleted_at` IS NULL LIMIT 1 FOR UPDATE', [$versionId, $productId, $supplierId]);
            if (! is_object($draft) || (string) $draft->status !== 'draft') { throw AuthException::conflict('Published API versions are immutable.'); }
            $c->update('UPDATE `api_versions` SET `version`=?,`name`=?,`summary`=?,`updated_at`=CURRENT_TIMESTAMP WHERE `id`=? AND `status`=\'draft\'', [$version, $name, $summary, $versionId]);
            $c->update('UPDATE `api_endpoints` SET `deleted_at`=CURRENT_TIMESTAMP,`updated_at`=CURRENT_TIMESTAMP WHERE `api_version_id`=? AND `deleted_at` IS NULL', [$versionId]);
            foreach ($endpoints as $endpoint) { $method = strtoupper(trim($endpoint['method'])); $path = trim($endpoint['path']); $endpointName = trim($endpoint['name']); $description=trim((string)($endpoint['description']??'')); if (! in_array($method, ['GET','POST','PUT','PATCH','DELETE'], true) || $path === '' || $path[0] !== '/' || strlen($path)>255 || $endpointName === '' || mb_strlen($endpointName)>128 || mb_strlen($description)>500) { throw AuthException::badRequest('Endpoint is invalid.'); } $c->insert('INSERT INTO `api_endpoints` (`id`,`api_version_id`,`method`,`path`,`name`,`description`,`created_at`,`updated_at`,`deleted_at`) VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,NULL) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`),`description`=VALUES(`description`),`deleted_at`=NULL,`updated_at`=CURRENT_TIMESTAMP', [$this->ids->generate(), $versionId, $method, $path, $endpointName, $description]); }
            $c->insert('INSERT INTO `api_documents` (`id`,`api_version_id`,`content_md`,`created_at`,`updated_at`,`deleted_at`) VALUES (?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,NULL) ON DUPLICATE KEY UPDATE `content_md`=VALUES(`content_md`),`deleted_at`=NULL,`updated_at`=CURRENT_TIMESTAMP', [$this->ids->generate(), $versionId, $documentation]);
            $c->insert('INSERT INTO `api_prices` (`id`,`api_version_id`,`unit_price_micros`,`currency`,`billing_unit`,`created_at`,`updated_at`,`deleted_at`) VALUES (?,?,?,?,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,NULL) ON DUPLICATE KEY UPDATE `unit_price_micros`=VALUES(`unit_price_micros`),`currency`=VALUES(`currency`),`deleted_at`=NULL,`updated_at`=CURRENT_TIMESTAMP', [$this->ids->generate(), $versionId, $priceMicros, $currency]);
            $c->insert('INSERT INTO `api_price_audit_logs` (`id`,`supplier_profile_id`,`api_product_id`,`api_version_id`,`old_unit_price_micros`,`new_unit_price_micros`,`currency`,`action`,`created_at`,`updated_at`,`deleted_at`) VALUES (?,?,?,?,?,?,?,\'draft_price_saved\',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,NULL)', [$this->ids->generate(),$supplierId,$productId,$versionId,isset($draft->unit_price_micros)?(int)$draft->unit_price_micros:null,$priceMicros,$currency]);
        });
    }

    /** 发布 `publish` 方法对应的数据或业务状态。 */
    public function publish(int $supplierId, int $versionId): void
    {
        $this->db->transaction(function (ConnectionInterface $c) use ($supplierId, $versionId): void {
            $v = $c->selectOne('SELECT v.`id`,v.`api_product_id`,v.`status`,v.`name`,v.`summary` FROM `api_versions` v INNER JOIN `api_products` p ON p.`id`=v.`api_product_id` WHERE v.`id`=? AND p.`supplier_profile_id`=? AND v.`deleted_at` IS NULL AND p.`deleted_at` IS NULL LIMIT 1 FOR UPDATE', [$versionId, $supplierId]);
            if (! is_object($v) || (string) $v->status !== 'draft') { throw AuthException::conflict('Only a draft version can be published.'); }
            $content = $c->selectOne('SELECT d.`id` FROM `api_documents` d WHERE d.`api_version_id`=? AND d.`deleted_at` IS NULL AND EXISTS (SELECT 1 FROM `api_endpoints` e WHERE e.`api_version_id`=d.`api_version_id` AND e.`deleted_at` IS NULL) LIMIT 1', [$versionId]);
            $price = $c->selectOne('SELECT `id` FROM `api_prices` WHERE `api_version_id`=? AND `deleted_at` IS NULL LIMIT 1', [$versionId]);
            if ($content === null || $price === null) { throw AuthException::badRequest('Draft content is incomplete.'); }
            $c->update('UPDATE `api_versions` SET `status`=\'published\',`published_at`=CURRENT_TIMESTAMP,`updated_at`=CURRENT_TIMESTAMP WHERE `id`=? AND `status`=\'draft\'', [$versionId]);
            $c->update('UPDATE `api_products` SET `name`=?,`summary`=?,`status`=\'published\',`current_published_version_id`=?,`updated_at`=CURRENT_TIMESTAMP WHERE `id`=?', [(string)$v->name,(string)$v->summary,$versionId, (int) $v->api_product_id]);
        });
    }

    /** 下架 `unlist` 方法对应的数据或业务状态。 */
    public function unlist(int $supplierId, int $productId): void { if ($this->db->update('UPDATE `api_products` SET `status`=\'unlisted\',`updated_at`=CURRENT_TIMESTAMP WHERE `id`=? AND `supplier_profile_id`=? AND `status`=\'published\' AND `deleted_at` IS NULL', [$productId, $supplierId]) !== 1) { throw AuthException::badRequest('Published API product does not exist.'); } }
    /** 创建 `createNextVersion` 方法对应的数据或业务状态。 */
    public function createNextVersion(int $supplierId, int $productId, string $version): int { $version = trim($version); if ($version === '' || strlen($version)>32) { throw AuthException::badRequest('Version is required.'); } return $this->db->transaction(function (ConnectionInterface $c) use ($supplierId, $productId, $version): int { $product=$c->selectOne('SELECT `id`,`current_published_version_id` FROM `api_products` WHERE `id`=? AND `supplier_profile_id`=? AND `current_published_version_id` IS NOT NULL AND `deleted_at` IS NULL LIMIT 1 FOR UPDATE', [$productId, $supplierId]); if ($product===null) { throw AuthException::badRequest('API product is unavailable.'); } if($c->selectOne('SELECT `id` FROM `api_versions` WHERE `api_product_id`=? AND `status`=\'draft\' AND `deleted_at` IS NULL LIMIT 1',[$productId])!==null){throw AuthException::conflict('Please finish the existing draft first.');}$id=$this->ids->generate(); $c->insert('INSERT INTO `api_versions` (`id`,`api_product_id`,`version`,`name`,`summary`,`status`,`published_at`,`created_at`,`updated_at`,`deleted_at`) SELECT ?,?,?,`name`,`summary`,\'draft\',NULL,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,NULL FROM `api_versions` WHERE `id`=? AND `status`=\'published\' AND `deleted_at` IS NULL', [$id,$productId,$version,(int)$product->current_published_version_id]); return $id; }); }

    /** 执行 `supplierProducts` 方法对应的业务处理。 @return list<array<string,mixed>> */
    public function supplierProducts(int $supplierId): array { return $this->rows($this->db->select('SELECT p.`id`,p.`name`,p.`slug`,p.`summary`,p.`status`,p.`current_published_version_id`,v.`id` AS `draft_version_id`,v.`version` AS `draft_version` FROM `api_products` p LEFT JOIN `api_versions` v ON v.`api_product_id`=p.`id` AND v.`status`=\'draft\' AND v.`deleted_at` IS NULL WHERE p.`supplier_profile_id`=? AND p.`deleted_at` IS NULL ORDER BY p.`created_at` DESC', [$supplierId])); }
    /** 执行 `market` 方法对应的业务处理。 @return list<array<string,mixed>> */
    public function market(): array { return $this->rows($this->db->select("SELECT p.`id`,v.`name`,p.`slug`,v.`summary`,v.`version`,pr.`unit_price_micros`,pr.`currency` FROM `api_products` p INNER JOIN `api_versions` v ON v.`id`=p.`current_published_version_id` AND v.`status` = 'published' AND v.`deleted_at` IS NULL INNER JOIN `api_prices` pr ON pr.`api_version_id`=v.`id` AND pr.`deleted_at` IS NULL WHERE p.`status` = 'published' AND p.`deleted_at` IS NULL ORDER BY v.`published_at` DESC, p.`id` DESC")); }
    /** 执行 `marketDetail` 方法对应的业务处理。 @return ?array<string,mixed> */
    public function marketDetail(int $productId): ?array { $row=$this->db->selectOne("SELECT p.`id`,v.`name`,p.`slug`,v.`summary`,v.`id` AS `version_id`,v.`version`,d.`content_md` AS `documentation`,pr.`unit_price_micros`,pr.`currency` FROM `api_products` p INNER JOIN `api_versions` v ON v.`id`=p.`current_published_version_id` AND v.`status`='published' INNER JOIN `api_documents` d ON d.`api_version_id`=v.`id` AND d.`deleted_at` IS NULL INNER JOIN `api_prices` pr ON pr.`api_version_id`=v.`id` AND pr.`deleted_at` IS NULL WHERE p.`id`=? AND p.`status`='published' AND p.`deleted_at` IS NULL LIMIT 1", [$productId]); if ($row===null) return null; $data=is_object($row)?get_object_vars($row):$row; $data['endpoints']=$this->rows($this->db->select('SELECT `method`,`path`,`name`,`description` FROM `api_endpoints` WHERE `api_version_id`=? AND `deleted_at` IS NULL ORDER BY `id`', [(int)$data['version_id']])); return $data; }

    /** 执行 `supplierDraft` 方法对应的业务处理。 @return ?array<string,mixed> */
    public function supplierDraft(int $supplierId, int $productId, int $versionId): ?array
    {
        $row = $this->db->selectOne("SELECT p.`id` AS `product_id`,v.`name`,p.`slug`,v.`summary`,p.`status` AS `product_status`,v.`id` AS `version_id`,v.`version`,v.`status` AS `version_status`,COALESCE(d.`content_md`,'') AS `documentation`,COALESCE(pr.`unit_price_micros`,0) AS `unit_price_micros`,COALESCE(pr.`currency`,'CNY') AS `currency` FROM `api_products` p INNER JOIN `api_versions` v ON v.`api_product_id`=p.`id` LEFT JOIN `api_documents` d ON d.`api_version_id`=v.`id` AND d.`deleted_at` IS NULL LEFT JOIN `api_prices` pr ON pr.`api_version_id`=v.`id` AND pr.`deleted_at` IS NULL WHERE p.`id`=? AND v.`id`=? AND p.`supplier_profile_id`=? AND p.`deleted_at` IS NULL AND v.`deleted_at` IS NULL LIMIT 1", [$productId, $versionId, $supplierId]);
        if ($row === null) { return null; }
        $data = is_object($row) ? get_object_vars($row) : $row;
        $data['endpoints'] = $this->rows($this->db->select('SELECT `method`,`path`,`name`,`description` FROM `api_endpoints` WHERE `api_version_id`=? AND `deleted_at` IS NULL ORDER BY `id`', [$versionId]));
        return $data;
    }

    /** 执行 `productFields` 方法对应的业务处理。 @return array{string,string,string} */
    private function productFields(string $name,string $slug,string $summary): array { $name=trim($name);$slug=trim($slug);$summary=trim($summary);if($name===''||mb_strlen($name)>128||preg_match('/^[a-z0-9][a-z0-9-]{2,95}$/D',$slug)!==1||mb_strlen($summary)>500) throw AuthException::badRequest('API product fields are invalid.');return[$name,$slug,$summary]; }
    /** 把数据库查询结果统一转换为数组列表。 @param list<object|array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function rows(array $rows): array { return array_map(static fn(object|array $r):array=>is_object($r)?get_object_vars($r):$r,$rows); }
}
