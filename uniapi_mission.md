# UniAPI 统一接口平台任务与接力记录

> 本文档是唯一任务和进度入口。架构与产品规则见 `uniapi.md`。任何 agent 中断恢复时必须先读两份文件，然后只执行“当前唯一任务”。

## 1. 当前状态

- 当前分支：`main`。
- 当前阶段：M1 UniAPI Web 系统总览。
- 最近业务提交：`ec60ffe feat: define administrator route permissions`。
- 最近完整验证：`composer test` 为 112 tests / 679 assertions；`composer analyse` 为 0 errors。
- 数据库状态：Schema 仅在单元测试中验证，未对运行中数据库执行变更。
- 工作方式：Web 界面优先；完成步骤后更新本文档、中文提交并推送 `origin main`。

## 2. 已完成基线

1. `e2124b8`：管理员七表 Schema 基线，全部表包含三个时间字段。
2. `4aa5f75`：受保护永久超级管理员 `welkin`，随机临时密码仅初始化时输出。
3. `6c7bd9c`：首次登录强制改密、会话撤销和改密页面。
4. `ec60ffe`：后台路由权限定义注册表，27 条路由定义通过 159 个断言。

## 3. 分阶段任务

### M0：规范和任务入口迁移

**状态：进行中**

1. [x] 汇总并确认 UniAPI 产品、计费、额度、结算和分布式部署决策。
2. [x] 用根目录 `uniapi.md` 替代旧 `progress.md`，写入框架、目录、流程图、Redis 和交付规范。
3. [x] 建立根目录 `uniapi_mission.md`，保留管理员/RBAC 已完成证据。
4. [x] 自检两份文档、确认旧入口已删除；`git diff --check` 无输出。
5. [ ] 已用中文提交 `de09300`；推送因当前环境未配置 GitHub HTTPS 凭据而阻塞，待认证后补推 `origin main`。

### M1：UniAPI Web 系统总览

**状态：未开始**

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
5. [ ] 更新任务证据，中文提交并推送 `origin main`。

### M2：管理员与 RBAC 闭环

**状态：未开始**

1. 权限同步服务：新增、恢复、停用系统权限且不覆盖自定义权限。
2. 多角色权限并集、角色/权限停用即时失效、超管服务端直通。
3. 权限中间件、菜单管理、管理员管理、角色/权限页面。
4. 永久数据库全量审计及只读日志页面。

### M3：双侧身份与工作台

**状态：未开始**

实现普通用户与管理员隔离，采购方/供应商业务身份，自动准入及各自工作台。

### M4：API 商品与市场

**状态：未开始**

实现 API、版本、文档、定价、发布状态和采购方市场页。

### M5：应用、密钥与订阅

**状态：未开始**

实现采购方应用、一次性密钥、API 开通和供应商可调的 QPS/周期额度。

### M6：分布式网关与计量

**状态：未开始**

实现签名鉴权、原子限流/额度、代理、幂等计量事件、消费者和重建验证。

### M7：账单、宽限期与结算

**状态：未开始**

实现月度后付费账单、付款凭证、人工确认、宽限期暂停、佣金和供应商结算。

## 4. 中断恢复规范

1. 运行 `git status --short`，不得覆盖非本任务改动。
2. 完整阅读 `uniapi.md` 和本文档。
3. 找到“当前唯一任务”，只执行其中第一个未完成编号步骤。
4. 先写失败测试；未观察到正确 RED 前不得实现对应生产代码。
5. 每次更新必须记录：完成步骤、文件、验证命令与结果、风险、下一唯一步骤。
6. 完成可验证步骤后使用中文 commit，推送 `origin main`，把结果写回本文档。

## 5. 当前唯一任务

完成 M1 第 5 步：选择性暂存页面、测试和本文档，中文提交后重试推送 `origin main`。远端认证恢复后必须同时推送尚未远端同步的 `de09300`。

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
- 风险：GitHub 凭据缺失，两个本地中文提交需要在认证恢复后推送。
- 下一断点：M1 第 5 步中文提交并重试推送；随后进入 M2 权限同步服务 RED。
