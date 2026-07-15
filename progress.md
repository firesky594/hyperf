# Agent Admin RBAC 项目进度与接力规范

> 本文档是该功能的唯一事实来源和接力入口。任何新 agent 或新会话继续开发前，必须先完整阅读本文档。不再创建或依赖 `docs/` 下的设计与计划文档。

## 一、项目目标与已确认决策

### 1.1 项目目标

在现有 Hyperf 3.1 `/agent_admin` 独立管理员认证基础上，建设终端风格的管理后台一期：

- 超级管理员初始化与首次登录强制改密
- RBAC 角色和权限管理
- 菜单管理
- 操作日志管理
- 管理员管理

### 1.2 已确认的业务决策

- 超级管理员用户名固定为 `welkin`。
- 初始密码由系统生成高强度随机密码，仅在初始化时展示一次；不写入源码、配置样例、日志或 Git。
- `welkin` 首次登录必须修改密码；改密前只能访问改密和退出功能。
- RBAC 采用“页面 + 操作”粒度，如管理员管理分为查看、新增、编辑、禁用、重置密码等权限。
- 系统权限可从路由元数据自动同步：路由增加时新增权限，路由移除时标记为停用，不物理删除已关联数据。
- 后台支持创建自定义权限，与路由同步的系统权限共存。
- 菜单完全由后台管理，可配置层级、名称、图标、排序、路由和所需权限；不从路由自动生成菜单。
- 操作日志采用数据库全量审计：记录登录、退出、失败登录、改密、列表查看、搜索、分页、新增、修改、启停、授权等所有后台操作。
- 审计日志必须保存到 MySQL，不仅依赖应用文件日志。
- 审计日志永久保存，采用追加写入、后台只读查询模型；不提供编辑、删除或清空功能，超级管理员也不例外。
- 管理员账号不允许物理删除，普通管理员只能启用或禁用，以保留审计日志中的操作者关联。
- `welkin` 为受保护的永久超级管理员：不允许删除、禁用或取消超级管理员身份；规则必须在服务端强制。
- 普通用户与管理员必须严格分表：普通用户使用现有 `users`，后台管理员使用 `admin_users`；两套认证、Token、会话和权限边界不得混用。
- `admin_users.is_super_admin` 作为超级管理员标记；`welkin` 在服务端直接通过所有后台权限检查，不依赖角色-权限关联。
- 普通管理员必须通过 RBAC 角色获得权限；`users` 表中的普通用户不参与本后台 RBAC。
- 管理员与角色是多对多关系：一个普通管理员可分配多个角色，一个角色可分配给多个管理员。
- 普通管理员的最终权限为所有已启用角色下已启用权限的并集；角色或权限停用后应立即不再生效。
- 本次新增或调整的所有后台业务表，包括多对多关联表和审计表，必须统一包含 `created_at`、`updated_at`、`deleted_at` 三个字段；`deleted_at` 默认为 `NULL`。
- 审计表虽按表结构规范保留 `updated_at` 和 `deleted_at`，但业务层不得更新或软删除审计记录。
- 视觉方向为终端风格，延续现有 Agent Admin 深色控制台基调，同时保证可读性、键盘操作和移动端适配。
- 不另外制作视觉稿；新后台页面直接沿用现有登录页和控制台的视觉语言，包括深色底、等宽字体、绿色状态色、细边框、命令行标签和克制动画。

### 1.3 现有代码基线

- 路由入口：`config/routes.php`
- 管理员登录控制器：`app/Controller/AgentAdminAuthController.php`
- 管理员首页控制器：`app/Controller/AgentAdminHomeController.php`
- 管理员认证服务：`app/Service/AdminAuthService.php`
- 管理员创建服务：`app/Service/AdminUserProvisioner.php`
- 管理员中间件：`app/Middleware/AdminAuthMiddleware.php`
- 后台页面渲染：`app/View/AgentAdminPageRenderer.php`
- 响应和 Cookie 封装：`app/Http/AgentAdminResponseFactory.php`
- 现有测试：`test/Cases/*Admin*Test.php` 及 `test/Cases/AgentAdmin*Test.php`

### 1.4 已确认架构

- 采用 Hyperf 模块化单体 + 服务端渲染，不引入独立 Vue/React SPA。
- 继续使用 MySQL 保存业务数据和审计日志，Redis 保存管理员会话与必要的短期并发状态。
- 内部拆分为管理员认证、管理员管理、RBAC、菜单、审计五个模块，每个模块使用独立控制器、服务和测试。
- 菜单可见性和路由授权是两道独立边界：菜单隐藏不能代替服务端权限校验。

### 1.5 数据模型

- `users`：原普通用户表，不参与后台 RBAC。
- `admin_users`：管理员账号，包含 `is_super_admin`、`must_change_password`、状态、最后登录信息。
- `admin_roles`：角色名称、唯一编码、状态和说明。
- `admin_permissions`：权限编码、名称、来源、关联路由和状态；来源区分系统路由和后台自定义。
- `admin_user_roles`：管理员与角色多对多关联。
- `admin_role_permissions`：角色与权限多对多关联。
- `admin_menus`：父级、名称、图标、排序、后台路由、绑定权限和状态。
- `admin_audit_logs`：操作者、动作、目标、请求摘要、结果、IP、User-Agent、耗时和请求追踪 ID。
- 上述所有本次新增或调整表，包括关联表和审计表，都必须有 `created_at`、`updated_at`、`deleted_at`。
- 所有新表主键使用项目现有雪花 ID；唯一约束需考虑软删除后的编码/名称重用策略。

### 1.6 页面与业务流程

- 导航顺序：系统总览、管理员管理、角色管理、权限管理、菜单管理、操作日志、修改密码、安全退出。
- 初始化命令创建或修复 `welkin`，生成随机临时密码并仅在当次终端输出。
- `must_change_password=1` 时，管理员只能访问改密和退出；改密后撤销当前会话并要求重新登录。
- 创建普通管理员时同样生成一次性临时密码；重置密码后撤销其全部会话并恢复强制改密状态。
- 管理员可编辑、启停、分配多个角色、重置密码；不提供物理删除。
- 角色可新增、编辑、启停和批量绑定权限。
- 权限页面区分系统路由权限和自定义权限；路由同步新增系统权限并停用已消失路由的系统权限，不覆盖自定义权限。
- 菜单完全手工管理，服务端按权限过滤；父菜单无直接权限但包含可见子菜单时仍需展示。
- 所有写操作使用 POST 并验证 CSRF；无权直接访问返回 403。

### 1.7 全量审计与错误处理

- 每次后台请求生成唯一 `request_id`。
- 审计记录包含操作者 ID、用户名快照、动作编码、请求方法、路由、IP、User-Agent、目标类型/ID、参数摘要、HTTP 状态、结果、开始/结束时间、耗时和三个时间字段。
- 审计覆盖登录、登录失败、限流、退出、改密、查询、搜索、分页、新增、修改、启停、授权等所有后台行为。
- 密码、确认密码、CSRF、Cookie、Authorization 和会话 Token 永不写入审计表；参数摘要通过字段白名单生成。
- 管理员、角色、权限和菜单的写操作与审计记录在同一 MySQL 事务中；审计失败则业务一起回滚。
- 查询类审计在响应完成前独立落库；落库失败返回 503，不允许未留痕访问。
- 查询审计列表本身需记录审计，但内部审计写入不产生递归日志。
- 管理员禁用或密码重置后立即撤销全部 Redis 会话；角色/权限变更立即生效，不将完整权限集固化在会话中。
- HTTP 语义：401 登录失败，403 权限不足，419 CSRF 失败，422 业务校验失败，429 登录限流，503 基础设施故障。

### 1.8 测试与验收设计

- 所有功能严格遵循 RED → GREEN → REFACTOR，先运行新测试并观察预期失败。
- 覆盖超管幂等初始化、随机密码一次输出、首次改密门禁和旧会话失效。
- 覆盖 `users`/`admin_users` 认证隔离、`welkin` 保护、多角色权限并集和角色/权限停用即时失效。
- 覆盖路由权限新增/停用同步、不覆盖自定义权限、菜单树过滤和父菜单保留。
- 覆盖所有写操作 CSRF、越权 403、管理员禁用/重置密码后会话撤销。
- 覆盖全量审计、敏感数据脱敏、写操作事务回滚、查询审计失败 503 与审计日志不可修改/删除。
- 页面验收覆盖现有终端风格一致性、键盘焦点、移动端和 `prefers-reduced-motion`。
- 完成前运行 `composer test` 和 `composer analyse`；宿主机缺少 Swoole 时使用项目 Hyperf Docker 镜像运行同等验证。

## 二、分步执行计划

### 阶段 1：需求、设计与实施计划

**状态：已完成**

**目标：** 不修改业务代码，完成可执行的数据模型、权限边界、界面结构、错误处理和测试设计。

**步骤：**

1. [已完成] 逐项确认超管、RBAC、菜单、日志和管理员管理的核心边界。
2. [已完成] 提出模块化单体、独立 SPA 和配置驱动三种方案；用户确认采用第一种。
3. [已完成] 分段向用户展示架构、数据模型、页面、安全和测试设计，并取得确认。
4. [已完成] 将完整设计和测试验收信息合并到根目录 `progress.md`，删除 `docs/` 旧文档，并完成自检。
5. [已完成] 将 `progress.md` 作为唯一设计来源提交；设计基线提交为 `b47a959`。
6. [已完成] 用户已复核并明确回复“确认执行”。
7. [已完成] 已用 writing-plans 方法在 `progress.md` 中追加并自检七个测试驱动详细实施任务。

**阶段门禁：** `progress.md` 完整设计完成自检并提交前，不得修改业务代码、数据库或运行中服务。

### 阶段 2：认证基线与 RBAC 核心

**状态：进行中**

**前置条件：** 阶段 1 在 `progress.md` 中的完整设计和详细实施任务已完成并提交。

**预期交付：**

1. 数据库表和可重复执行的初始化/迁移机制。
2. `welkin` 随机临时密码初始化，以及首次登录强制改密门禁。
3. 角色、权限、管理员-角色、角色-权限关联模型。
4. 路由权限元数据和权限同步命令，支持新增及停用，不破坏自定义权限。
5. 认证中间件、首次改密中间件和权限校验中间件。
6. 超级管理员保护，避免自我禁用、删除或失去超管能力。

**执行规范：** 每个行为严格遵循 RED → GREEN → REFACTOR，先运行新测试并看到预期失败，再编写最小实现。

### 阶段 3：后台模块、终端界面与验收

**状态：未开始**

**前置条件：** 阶段 2 的认证和 RBAC 核心已通过独立测试及回归测试。

**预期交付：**

1. 角色与权限管理页面，包括系统/自定义权限状态。
2. 菜单管理页面，支持层级、名称、图标、排序、路由、权限绑定和启停。
3. 管理员管理页面，支持创建、编辑、禁用/启用、角色分配和密码重置。
4. 操作日志查询页面，支持检索数据库中永久保存的全量后台审计记录；页面不提供任何修改或删除操作。
5. 终端风格布局、状态反馈、键盘焦点、响应式布局和减少动画偏好支持。
6. PHPUnit、PHPStan、完整回归和关键 HTTP 流程验证。
7. 更新项目功能和运维文档，包含超管初始化、权限同步、密码找回和日志保留操作。

## 三、中断恢复与更新规范

### 3.1 当前精确断点

- 当前阶段：阶段 2 / 任务 3 RBAC、路由元数据与权限同步。
- 最后完成：任务 2 超管初始化、幂等修复、首次改密、会话撤销、路由门禁和终端改密页，完整回归通过。
- 下一步：提交任务 2，然后为后台路由权限编码唯一性和方法/路径绑定编写 RouteRegistry RED。
- 当前未修改运行中数据库或服务；Schema 仅通过 Mockery 单元测试验证，尚未对线上数据库执行。

### 3.2 本轮更新证据

- 完成步骤：阶段 1 步骤 4。
- 新增/修改：新建根目录 `progress.md`，作为唯一设计与接力来源。
- 删除：`docs/` 下 7 个旧分析、设计和计划文件；该目录已无文件。
- 验证：搜索 `docs/|TBD|TODO|待确认|设计文档|实施计划`，只剩“不依赖 docs”等刻意的规范说明；`git diff --check` 无输出，格式通过。
- 未解决：业务代码尚未开始修改；任务 1 的 Schema 测试尚未创建。
- 下一个唯一步骤：提交详细实施任务后，执行任务 1 步骤 1。
- 提交证据：`b47a959 docs: establish agent admin rbac progress source`。

### 3.3 新 agent 接力顺序

1. 运行 `git status --short` 确认工作树，不得覆盖或回退用户和其他 agent 的改动。
2. 完整阅读本文档，核对“当前精确断点”。
3. 在本文档中查找“当前精确断点”和各阶段第一个未完成编号步骤；不从 `docs/` 寻找旧计划。
4. 只执行当前阶段的下一个未完成步骤；不跨过用户确认门禁。
5. 每完成一个可验证步骤，先更新本文档的状态、证据和下一断点，再进入下一步。

### 3.4 进度更新必填项

每次更新本文档必须同时写明：

1. 完成了哪个编号步骤。
2. 新增或修改的文件。
3. 运行过的验证命令及结果。
4. 未解决的问题或风险。
5. 下一个唯一步骤。

### 3.5 通用安全与开发约束

- 不在 Git 可跟踪文件、测试快照或日志中保存临时密码、会话 Token 或密码明文。
- 超级管理员保护规则必须在服务层强制，不能只依赖前端隐藏按钮。
- 权限校验必须在服务端路由/中间件边界执行。
- 所有修改遵循测试驱动；没有看到新测试正确失败前，不写对应生产代码。
- 每次提交只包含当前小步骤的文件，不夹带工作树中的无关改动。
- 声称完成前，必须记录实际验证命令和输出结果。

## 四、测试驱动详细实施任务

> 当前会话内串行执行。每项严格按“失败测试 → 记录 RED → 最小实现 → 记录 GREEN → 回归 → 更新本文档 → 单独提交”推进。

### 任务 1：数据库结构基线

**状态：已完成**

**文件：** 新建 `app/Service/AdminSchemaService.php`、`app/Command/AdminSchemaCommand.php`、`test/Cases/AdminSchemaServiceTest.php`；修改 `AdminUserProvisioner.php` 依赖统一 Schema 服务。

**接口：** `AdminSchemaService::ensureSchema(): void`；命令 `admin:schema`。

1. [x] 测试七张后台表、必要索引和所有表的 `created_at/updated_at/deleted_at`。
2. [x] Docker 运行定向 PHPUnit，正确 RED：`Class "App\Service\AdminSchemaService" not found`。
3. [x] 实现幂等 DDL，升级 `admin_users` 并新增其余六张表。
4. [x] 定向 GREEN：5 tests / 41 assertions；完整回归：100 tests / 647 assertions；PHPStan 0 errors。
5. [x] 已更新进度，待提交 `feat: add administrator schema baseline`。

### 任务 2：超管初始化与首次改密

**状态：已完成**

**文件：** 修改 Provisioner、SetupCommand、AuthService、AuthMiddleware 和路由；新建 `AdminPasswordService.php`、`AdminPasswordChangeMiddleware.php`、`AgentAdminPasswordController.php` 及测试。

**接口：** `provisionSuperAdmin(string $username = 'welkin'): array`、`changePassword(int $adminId, string $current, string $new): void`、`resetPassword(int $adminId): string`、`revokeAdminSessions(int $adminId): void`。

1. [x] 测试 `welkin` 幂等初始化、超管标识、强制改密、至少 20 位随机密码且仅当次返回。
2. [x] 看见 Provisioner 和命令 RED 后实现 GREEN。
3. [x] 测试首次改密路由门禁、密码校验、CSRF、改密后会话撤销和重新登录。
4. [x] 实现改密服务、中间件、控制器和终端风格页面。
5. [x] 新增/定向测试通过；完整回归 112 tests / 679 assertions；PHPStan 0 errors。
6. [x] 已更新进度，待提交 `feat: require administrator password rotation`。

**进行中证据：**

- RED 1：`Call to undefined method AdminUserProvisioner::provisionSuperAdmin()`。
- GREEN 1：超管创建服务测试通过。
- RED 2：旧命令报 `Not enough arguments (missing: "username")`。
- GREEN 2：`AdminSetupCommandTest` 与 Provisioner 测试共 6 tests / 11 assertions。
- 当前完整回归：102 tests / 652 assertions；PHPStan 0 errors。
- 后续 RED：已存在 `welkin` 修复时错误走 INSERT；修复后 6 tests / 8 assertions。
- 改密服务 RED：`Class "App\Service\AdminPasswordService" not found`。
- 会话撤销 RED：`Call to undefined method AdminAuthService::revokeAdminSessions()`。
- 会话索引 RED：登录未调用 `sAdd`。
- 门禁 RED：`AdminPasswordChangeMiddleware` 不存在。
- HTTP RED：改密 GET/POST 均为 404。
- 最终 GREEN：完整回归 112 tests / 679 assertions；PHPStan 0 errors。

### 任务 3：RBAC、路由元数据与权限同步

**状态：进行中**

**文件：** 新建 `app/Rbac/AdminRouteDefinition.php`、`AdminRouteRegistry.php`、`AdminPermissionService.php`、`AdminRoleService.php`、`AdminPermissionMiddleware.php`、`AdminPermissionSyncCommand.php` 及测试；修改路由。

**接口：** `definitions(): array`、`syncSystemPermissions(): array`、`hasPermission(array $session, string $code): bool`、`replaceRolePermissions(int $roleId, array $permissionIds, int $actorId): void`、`replaceAdminRoles(int $adminId, array $roleIds, int $actorId): void`。

1. [ ] 测试权限编码唯一以及方法、路径、权限绑定完整。
2. [ ] 测试系统权限新增、消失停用、重新出现恢复且不修改自定义权限。
3. [ ] 测试超管直通、多角色权限并集、停用/软删除立即失效和无权 403。
4. [ ] 逐项看见 RED 后实现 Registry、同步命令、权限服务和中间件。
5. [ ] 运行 RBAC 定向测试和认证回归。
6. [ ] 更新进度并提交 `feat: add administrator rbac enforcement`。

### 任务 4：数据库全量审计

**状态：待执行**

**文件：** 新建 `app/Audit/AdminAuditContext.php`、`AdminAuditEntry.php`、`AdminAuditSanitizer.php`、`AdminAuditService.php`、`AdminAuditMiddleware.php` 及测试。

**接口：** `sanitize(array $input, array $allowedFields): array`、`record(AdminAuditEntry $entry): void`、`withinTransaction(callable $operation, AdminAuditEntry $entry): mixed`。

1. [ ] 测试密码、确认密码、CSRF、Cookie、Authorization、Token 在嵌套和大小写变化下均不落库。
2. [ ] 测试请求 ID、操作者快照、读写/失败事件和审计查询不递归。
3. [ ] 测试写操作与审计同事务回滚，查询审计失败返回 503。
4. [ ] 逐项看见 RED 后实现脱敏器、值对象、服务和中间件。
5. [ ] 运行审计定向测试和全部认证/RBAC 回归。
6. [ ] 更新进度并提交 `feat: add immutable administrator audit trail`。

### 任务 5：菜单树与终端布局

**状态：待执行**

**文件：** 新建 `AdminMenuService.php`、`AgentAdminLayoutRenderer.php`、`AgentAdminMenuRenderer.php` 及测试；从现有 PageRenderer 拆出公共布局；修改首页控制器。

**接口：** `visibleTree(array $session): array`、`render(string $title, string $content, array $menuTree, array $session): string`。

1. [ ] 测试启停、权限、排序、软删除、父菜单保留和循环父级防护。
2. [ ] 看见 RED 后实现最小可见菜单树。
3. [ ] 测试导航、当前项、超管标识、CSRF 退出、键盘焦点、移动端和减少动画 CSS。
4. [ ] 实现沿用当前登录页视觉的公共布局并回归现有渲染测试。
5. [ ] 更新进度并提交 `feat: add permission-aware admin navigation`。

### 任务 6：五个后台管理模块

**状态：待执行**

**文件：** 分别新建管理员、角色、权限、菜单、审计日志控制器和聚焦型 Renderer；扩展对应服务；通过 RouteRegistry 注册页面及 POST 路由；每个模块建立控制器、服务和渲染测试。

**统一约束：** 默认每页 20 条、最大 100 条；排除软删除；写操作必须经过 POST、CSRF、权限中间件和同事务审计。

1. [ ] 管理员：列表/搜索/分页、创建、编辑、启停、多角色、重置密码、会话撤销、`welkin` 保护。
2. [ ] 角色：列表、创建、编辑、启停、批量替换权限。
3. [ ] 权限：系统/自定义区分、自定义维护、系统同步和系统字段保护。
4. [ ] 菜单：树形展示、创建、编辑、启停、父级/图标/排序/路由/权限绑定。
5. [ ] 审计：只读列表、筛选、分页、详情；不存在编辑、删除、清空路由。
6. [ ] 每个模块分别完成 RED/GREEN/回归/进度更新和独立提交。

### 任务 7：集成验收

**状态：待执行**

**文件：** 扩展 HTTP 测试；更新 `README.md` 为真实项目说明；持续维护本文件，不恢复 `docs/`。

1. [ ] 验证超管初始化 → 临时密码登录 → 强制改密 → 旧会话失效 → 新密码登录。
2. [ ] 验证权限/角色/菜单/管理员创建、多角色授权及停用后立即 403。
3. [ ] 验证所有读写均产生脱敏审计、审计不可修改删除、审计故障 503。
4. [ ] 运行 `composer test`；宿主机缺 Swoole 时使用项目 Hyperf Docker 镜像。
5. [ ] 运行 `composer analyse`、`git diff --check` 和敏感信息扫描。
6. [ ] 记录实际命令、输出、最终提交号和已知风险后才能宣告完成。
