# UniAPI 统一接口平台任务与接力记录

> 本文档是唯一任务和进度入口。架构与产品规则见 `uniapi.md`。任何 agent 中断恢复时必须先读两份文件，然后只执行“当前唯一任务”。

## 1. 当前状态

- 当前分支：`main`。
- 当前阶段：M5 应用、密钥与订阅 / Web 页面。
- 最近业务提交：`4e3f9dd 功能：完成API商品市场与版本发布闭环`。
- 最近完整验证：`composer test` 为 163 tests / 1089 assertions；`composer analyse` 为 0 errors。
- 数据库状态：运行容器已成功执行管理员、双侧身份和 API 商品 Schema；22 项系统权限已同步；随机注册真实事务返回 201。
- 工作方式：Web 界面优先；完成步骤后更新本文档、中文提交并推送 `origin main`。

## 2. 已完成基线

1. `e2124b8`：管理员七表 Schema 基线，全部表包含三个时间字段。
2. `4aa5f75`：受保护永久超级管理员 `welkin`，随机临时密码仅初始化时输出。
3. `6c7bd9c`：首次登录强制改密、会话撤销和改密页面。
4. `ec60ffe`：后台路由权限定义注册表，27 条路由定义通过 159 个断言。

## 3. 分阶段任务

### M0：规范和任务入口迁移

**状态：已完成**

1. [x] 汇总并确认 UniAPI 产品、计费、额度、结算和分布式部署决策。
2. [x] 用根目录 `uniapi.md` 替代旧 `progress.md`，写入框架、目录、流程图、Redis 和交付规范。
3. [x] 建立根目录 `uniapi_mission.md`，保留管理员/RBAC 已完成证据。
4. [x] 自检两份文档、确认旧入口已删除；`git diff --check` 无输出。
5. [x] 中文提交 `de09300`，2026-07-15 已确认 `origin main` 为最新。

### M1：UniAPI Web 系统总览

**状态：已完成**

**目标：** 延续现有登录页视觉，在 `/agent_admin` 首屏展示平台身份、API 市场、应用调用、账单结算和分布式节点状态的真实入口/状态。

**文件：**

- 修改 `app/View/AgentAdminPageRenderer.php`。
- 修改或新建 `test/Cases/AgentAdminPageRendererTest.php`。
- 仅在页面需要真实数据契约时修改 `app/Controller/AgentAdminHomeController.php`。

**步骤：**

1. [x] 写系统总览布局、关键文案、键盘焦点和移动端行为测试。
2. [x] 容器运行定向测试并得到预期 RED：缺少 UniAPI 页面标题，1 test / 1 failure。
3. [x] 实现最小终端风格总览，未接通模块明确显示“尚未接入实时数据”。
4. [x] 渲染器 9 tests / 84 assertions；完整回归 114 tests / 853 assertions；PHPStan 0 errors。
5. [x] 中文提交 `0b96425`，任务同步提交 `33da601`；2026-07-15 已确认远端最新。

### M2：管理员与 RBAC 闭环

**状态：已完成**

1. [x] 权限同步服务与命令：新增、恢复、停用系统权限，不覆盖自定义权限。
2. [x] Web 先行：管理员、角色、权限、菜单、审计导航与真实空状态页面。
3. [x] 多角色权限并集、角色/权限停用即时失效、超管服务端直通及权限中间件。
4. [x] 管理员、角色、权限、菜单 CRUD；所有写操作 POST + CSRF + 事务审计。
5. [x] 永久数据库全量审计、敏感字段过滤、只读检索页面和查询审计失败关闭。
6. [x] 完整测试、静态分析、更新记录、中文提交并推送。

### M3：双侧身份与工作台

**状态：已完成**

1. [x] Web 先行：采购方、供应商工作台与身份切换入口。
2. [x] 新增业务身份/资料表，严格复用 `users` 而不混用 `admin_users`。
3. [x] 注册后自动创建采购方身份，供应商资料可申请/维护且不影响采购身份。
4. [x] 双工作台控制器、服务、认证边界、测试、中文提交并推送。

### M4：API 商品与市场

**状态：已完成**

1. [x] Web 先行：供应商 API 管理、编辑页和采购方市场/详情页。
2. [x] 新增 API、版本、端点、文档、按量价格和永久价格审计表及 Schema 测试。
3. [x] 草稿、发布、下架状态机；已发布版本使用独立快照且不可原地篡改。
4. [x] 市场只展示当前可售版本，价格使用百万分之一货币单位整数。
5. [x] 完整测试、静态分析、运行容器 Schema/HTTP 验收、review、中文提交并推送。

### M5：应用、密钥与订阅

**状态：已完成**

1. [x] Web 先行：应用、一次性密钥提示、订阅和额度管理页。
2. [x] 新增应用、密钥摘要、订阅、额度策略表及 Schema 测试。
3. [x] 密钥创建/重置仅返回一次明文，数据库只存安全摘要并支持撤销。
4. [x] 开通 API 建立订阅；供应商可按采购方调整 QPS 和周期额度并审计。
5. [x] 完整测试、静态分析、中文提交并推送。

### M6：分布式网关与计量

**状态：已完成**

1. [x] Web 先行：网关路由、调用日志、用量与节点角色状态页。
2. [x] `APP_ROLE` 配置与 control-plane/gateway/metering/billing/notification/all-in-one 启动边界。
3. [x] 请求签名、防重放、上游路由和失败关闭错误码。
4. [x] Redis Cluster hash tag + Lua 原子 QPS/额度检查扣减，热路径无 KEYS/SCAN。
5. [x] Stream 计量事件、`event_id` 幂等落库、消费者组恢复与数据库重建测试。
6. [x] 完整测试、静态分析、中文提交并推送。

### M7：账单、宽限期与结算

**状态：未开始**

1. [ ] Web 先行：采购账单、付款凭证、平台确认、供应商结算页。
2. [ ] 新增账单、账单项、付款凭证、佣金配置、结算单表及 Schema 测试。
3. [ ] 按账期和计量快照幂等出账，账单生成后不可改写历史价格/佣金。
4. [ ] 固定宽限期、期满暂停、确认付款恢复；任务以租约 + 唯一键防重复。
5. [ ] 供应商佣金计算、应结金额、人工确认与永久审计。
6. [ ] 完整测试、静态分析、端到端关键流程、中文提交并推送。

## 4. 中断恢复规范

1. 运行 `git status --short`，不得覆盖非本任务改动。
2. 完整阅读 `uniapi.md` 和本文档。
3. 找到“当前唯一任务”，只执行其中第一个未完成编号步骤。
4. 先写失败测试；未观察到正确 RED 前不得实现对应生产代码。
5. 每次更新必须记录：完成步骤、文件、验证命令与结果、风险、下一唯一步骤。
6. 完成可验证步骤后使用中文 commit，推送 `origin main`，把结果写回本文档。

## 5. 当前唯一任务

执行 M7 第 1 步 Web 页面切片：先为采购账单、付款凭证、平台确认和供应商结算页编写 RED。

## 6. 本轮证据

- 已确认：一个 Hyperf 代码库按 `APP_ROLE` 分布式部署；Redis Cluster 原子限流、幂等消费、失败关闭、MySQL 最终事实。
- 已确认：后付费月结、供应商设置 QPS/周期额度、固定宽限期、付款凭证人工确认、供应商佣金率。
- 已确认：优先修改 Web 界面，以真实显示便于人工指导。
- 文档自检：`rg -n "progress\\.md|docs/|TBD|TODO|待确认" uniapi.md uniapi_mission.md` 仅命中迁移和禁用旧入口的刻意说明；无占位项。
- 格式自检：`git diff --check` 无输出；旧 `progress.md` 已进入删除状态。
- M0 提交：`de09300 文档：建立统一接口平台规范与任务入口`。
- M0 推送：失败，`gh auth status` 显示未登录，HTTPS remote 无法读取 GitHub 用户名；本地提交保持完整。
- M1 RED：容器定向测试 1 test / 1 failure，失败点为旧页面缺少 UniAPI 标题。
- M1 GREEN：`AgentAdminPageRendererTest` 为 9 tests / 84 assertions。
- M1 回归：容器 `composer test` 为 114 tests / 853 assertions；`composer analyse` 为 0 errors。
- M1 文件：`app/View/AgentAdminPageRenderer.php`、`test/Cases/AgentAdminPageRendererTest.php`、`test/Cases/AgentAdminControllerTest.php`。
- M1 提交：`0b96425 界面：升级统一接口平台系统总览`。
- M1 推送：第二次尝试仍失败，错误为无法读取 GitHub HTTPS 用户名。
- Git 恢复：2026-07-15 执行 `git push origin main` 返回 `Everything up-to-date`，此前提交已同步。
- 总体执行：用户要求持续推进至 M7；每个阶段必须按上方编号留下验证、提交、推送和唯一断点。
- M2.1 RED：`AdminPermissionService` 不存在；命令测试在调整服务可测试性后正确失败于 `AdminPermissionSyncCommand` 不存在。
- M2.1 GREEN：新增 `AdminPermissionService`，事务内新增/恢复/停用系统权限并跳过同码自定义权限；新增 `admin:permissions:sync` 命令。
- M2.1 文件：`app/Service/AdminPermissionService.php`、`app/Command/AdminPermissionSyncCommand.php`、`config/autoload/commands.php`、两个对应测试。
- M2.1 验证：定向 2 tests / 13 assertions；完整回归 116 tests / 866 assertions；PHPStan 0 errors。
- M2.1 风险：尚未对运行中数据库执行同步命令；待 M2 页面与审计闭环完成后统一做受控环境验收。
- M2.2 RED：渲染器测试失败于 `AgentAdminPageRenderer::management()` 不存在。
- M2.2 GREEN：新增共享 RBAC 导航、五个终端风格真实空状态页面、管理控制器及五条认证/改密门禁路由。
- M2.2 文件：`app/View/AgentAdminPageRenderer.php`、`app/Controller/AgentAdminManagementController.php`、`config/routes.php`、渲染与 HTTP 测试。
- M2.2 验证：渲染器 10 tests / 119 assertions；五路由 HTTP 1 test / 40 assertions；完整回归 118 tests / 941 assertions；PHPStan 0 errors。
- M2.2 风险：页面尚未连接数据库，已明确显示真实空状态；权限中间件将在 M2.3 接入。
- M2.3 RED：权限服务与中间件测试分别失败于对应类不存在。
- M2.3 GREEN：普通管理员权限每次通过管理员—多角色—权限关联实时查询；管理员、角色、权限停用或软删除立即失效；超管服务端直通。
- M2.3 安全边界：受保护路由从注册表取得权限编码，无授权返回安全 403，缺少路由元数据时失败关闭。
- M2.3 文件：`app/Service/AdminAuthorizationService.php`、`app/Middleware/AdminPermissionMiddleware.php`、`config/routes.php`、403 页面与对应测试。
- M2.3 验证：定向 6 tests / 20 assertions；管理页面 HTTP 40 assertions；完整回归 124 tests / 961 assertions；PHPStan 0 errors。
- M2.3 风险：普通管理员 HTTP 集成尚需在角色 CRUD 接通真实数据库后验收；当前服务单测已覆盖允许/拒绝和停用查询条件。
- M2.4 审计基线 RED/GREEN：`AdminAuditService` 缺失后实现只追加数据库写入；接口不提供更新或删除，递归过滤密码、CSRF、Cookie、Authorization、Token、Secret 与签名字段。
- M2.4 审计基线文件：`app/Service/AdminAuditService.php`、`test/Cases/AdminAuditServiceTest.php`。
- M2.4 审计基线验证：定向 1 test / 8 assertions；完整回归 125 tests / 969 assertions；PHPStan 0 errors。
- M2.4 管理员服务：列表不读取密码摘要；创建返回一次性临时密码；禁用、角色分配、密码重置均在事务内审计。
- M2.4 管理员安全：`welkin`/超级管理员不可禁用或分配普通角色；普通管理员禁用与重置密码后撤销全部 Redis 会话；关联表使用软撤销及恢复。
- M2.4 管理员文件：`app/Service/AdminUserManagementService.php`、`test/Cases/AdminUserManagementServiceTest.php`；`AdminAuditService` 调整为可替换依赖以便验证事务协作。
- M2.4 管理员验证：定向 6 tests / 11 assertions；完整回归 131 tests / 980 assertions；PHPStan 0 errors。
- M2.4 风险：管理员 HTTP 表单与 CSRF 尚未接入，将在角色/权限/菜单服务完成后统一连接现有 Web 页面。
- M2.4 角色服务 RED：新增 5 个行为测试，全部正确失败于 `AdminRoleManagementService` 不存在。
- M2.4 角色服务 GREEN：实现角色列表、创建、编辑、启停和批量绑定权限；关联通过软撤销/恢复替换，停用或软删除权限不可绑定。
- M2.4 角色事务边界：创建、编辑、启停和权限绑定均与 `AdminAuditService::append()` 使用同一数据库事务。
- M2.4 角色文件：`app/Service/AdminRoleManagementService.php`、`test/Cases/AdminRoleManagementServiceTest.php`。
- M2.4 角色验证：定向 5 tests / 9 assertions；完整回归 136 tests / 989 assertions；PHPStan 0 errors。
- M2.4 角色风险：尚未接入 HTTP 表单与运行中数据库；将在权限、菜单服务完成后统一连接 Web 页面并做受控验收。
- M2 闭环：新增自定义权限与菜单管理服务、真实数据库列表/表单、POST 路由、会话 CSRF、管理员编辑和永久审计只读查询；系统权限仍由同步服务独占管理。
- M2 查询安全：审计查询固定最多返回 100 条，数据库异常转换为 503，管理写操作继续递归过滤密码、Cookie、Token、Secret 与签名字段。
- M2 运行兼容：真实数据库不支持 `ADD COLUMN IF NOT EXISTS`；已改为 `SHOW COLUMNS` 后按缺失列执行标准 ALTER，定向 2 tests / 39 assertions。
- M2 运行验收：`admin:schema` 成功；`admin:permissions:sync` 创建 22 项系统权限，无恢复、停用或自定义冲突。
- M3 Schema：新增 `buyer_profiles`、`supplier_profiles`，均使用雪花 ID、唯一 `user_id` 和三个生命周期时间字段；未引用 `admin_users`。
- M3 注册事务：随机注册在同一 MySQL 事务写入 `users` 与默认采购方资料；真实 `/auth/register-random` 返回 201，一次性测试凭据临时文件已删除。
- M3 双侧身份：供应商申请/维护只写 `supplier_profiles`，不改变采购身份；工作台同时展示两个独立身份状态。
- M3 Web 与门禁：新增 `/portal/login`、`/workspace`、采购方/供应商工作台、身份切换入口和供应商申请/更新；Cookie 为 HttpOnly + SameSite Strict，同时支持 Bearer Token。
- M3 HTTP 验收：绕过本机代理直连后登录页返回 200；未认证工作台返回 303 到 `/portal/login` 并清除无效会话 Cookie。
- M2/M3 最终验证：`composer test` 为 154 tests / 1035 assertions；`composer analyse` 为 0 errors；`git diff --check` 无输出。
- M2/M3 业务提交：`76f5a3a 功能：闭环管理后台并完成双侧身份工作台`。
- 下一断点：M4 第 1 步 API 管理、市场和详情 Web 页面 RED，不提前创建 M4 数据表。
- M4 RED：页面、Schema、领域服务共 8 tests，均正确失败于 `CatalogPageRenderer`、`CatalogSchemaService`、`CatalogService` 不存在。
- M4 Web：新增供应商 API 管理/版本编辑、采购方市场/详情页及工作台导航；所有写操作均为 POST + 会话 CSRF，未认证路由由用户中间件保护。
- M4 数据：新增商品、版本、端点、文档、按量价格和价格审计六张表；所有表含三个生命周期时间字段，金额使用 `unit_price_micros` 整数。
- M4 状态机：创建初始草稿、保存、发布、下架和创建后续草稿均校验供应商归属；发布版本为独立名称/简介/文档/端点/价格快照，禁止原地修改。
- M4 市场：仅查询商品当前已发布版本；下架后详情返回不存在；市场按版本发布时间排序。
- M4 审计：草稿价格每次保存均在同一 MySQL 事务追加永久价格审计，记录旧值、新值、供应商、商品和版本。
- M3/M4 review 修复：数组表单输入不再被强制转字符串；Cookie 可通过 `USER_COOKIE_SECURE=true` 启用 Secure；限制单版本最多 100 个端点、文档 1 MB，并阻止同商品并存多个草稿。
- M4 定向验证：9 tests / 54 assertions；最终完整回归 163 tests / 1089 assertions；PHPStan 0 errors；`git diff --check` 无输出。
- M4 运行验收：容器内 `uniapi:catalog-schema` 成功创建六张表；容器内访问 `/market` 返回 303 到 `/portal/login`，认证边界和安全响应头生效。
- M4 review 结论：已修复市场排序引用不存在字段、草稿影响已发布展示、价格缺少永久审计、生产 Cookie 无 Secure 开关和输入资源上限五类问题；未发现遗留阻断项。
- M4 业务提交：`4e3f9dd 功能：完成API商品市场与版本发布闭环`。
- 下一断点：M5 第 1 步应用、一次性密钥提示、订阅和额度管理 Web 页面 RED，不提前创建 M5 数据表。
- M5 完成：应用与一次性密钥、订阅、供应商额度页面和五张业务表闭环；密钥只存 SHA-256 摘要，重置撤销旧密钥，额度变更同事务永久审计。
- M5 验证：定向 6 tests / 35 assertions；完整回归 169 tests / 1124 assertions；PHPStan 0 errors；运行容器 `uniapi:application-schema` 成功。
- 下一断点：M6 第 1 步网关、调用日志、用量和节点角色状态页面 RED。
- M6 完成：角色边界、密钥摘要解析与 HMAC 时间窗/nonce 防重放、HTTPS 上游路由、同槽 Lua QPS/周期额度、Stream 事件、幂等落库、消费者组恢复和数据库聚合重建闭环。
- M6 review：修复 Lua 在 QPS 拒绝时仍消耗周期额度的问题；禁止上游 URL 内嵌凭据并强制 HTTPS；Redis 异常保持失败关闭。
- M6 验证：定向 6 tests / 18 assertions；完整回归 175 tests / 1143 assertions；PHPStan 0 errors；运行容器 `uniapi:metering-schema` 成功。
- 下一断点：M7 第 1 步采购账单、付款凭证、平台确认和供应商结算页 RED。
