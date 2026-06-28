# 订单处理配置多处理人设计

## 背景

当前 `product_processing_craft` 已建立订单处理人、图画处理人和采购处理人的单员工 ID 字段，历史回填服务也会把每类处理人关联到一名员工。订单处理配置的维护页面尚未实施，因此现在可以在不返工页面的情况下改为多员工选择。

三个处理人类别都允许选择多名员工，同一类别内的员工地位相同，不区分主处理人。

## 方案选择

采用统一关联表，不在业务表中保存逗号分隔的员工 ID。

逗号分隔字段虽然实现简单，但无法建立员工外键，难以按员工反查配置，也无法可靠处理员工停用、软删除和输入校验。统一关联表可以保留关系完整性，并允许后续增加新的处理人类别而不新增更多表。

## 数据结构

新增 `product_processing_craft_employee_assignment`：

- `id`
- `product_processing_craft_id`
- `employee_id`
- `assignment_type`，使用字符串，不使用数据库 enum
- timestamps

首批 `assignment_type`：

- `order_processing`
- `artwork_processing`
- `procurement`

约束与索引：

- 唯一键：`product_processing_craft_id + employee_id + assignment_type`
- 配置和员工使用外键
- 增加处理类型、配置和员工的组合查询索引
- 配置及员工仍采用软删除；历史关联不因软删除而移除

## 兼容策略

现有字段继续保留：

- `order_processor_employee_id`
- `artwork_processor_employee_id`
- `procurement_processor_employee_id`

这些字段只作为本期之前的单员工历史兼容字段，不再作为新页面的数据来源，也不把多选结果中的任意员工解释为“主处理人”。新代码以关联表为准。

新增迁移会把三个旧字段中已有的非空员工 ID 分别写入关联表。历史文本字段 `order_processor`、`artwork_processor`、`procurement_processor` 保持原值，不因迁移而覆盖。

`BusinessDataBackfillService` 继续解析历史单人文本，并以幂等方式补充关联表；为兼容现有数据，它可以继续维护旧单员工 ID，但新的网页保存流程不再写入这些字段。

## 模型接口

`ProductProcessingCraft` 提供三组关系：

- `orderProcessorEmployees()`
- `artworkProcessorEmployees()`
- `procurementProcessorEmployees()`

三组关系都通过统一关联表和对应 `assignment_type` 过滤。页面、控制器和列表展示只使用这些多员工关系。

## 页面与保存流程

订单处理配置表单提供三个可搜索多选框：

- 订单处理人：候选员工需拥有订单处理职位
- 图画处理人：候选员工需拥有图画处理职位
- 采购处理人：候选员工需拥有采购职位

保存时分别验证员工存在、未软删除、处于启用状态并拥有对应职位，然后按处理类型同步关联表。一个员工可因拥有多个职位而同时出现在多个处理人类别中。

列表页将每类员工姓名用顿号连接展示。没有分配时显示空值，不回退显示逗号 ID。

采购结算方式仍属于订单处理配置本身，不拆成每位采购处理人的独立结算方式。

## 权限与删除

多选不改变现有权限规则：拥有 `order_processing.manage` 的账号可以维护三类处理人。

停用或软删除员工后，该员工不再出现在新选择候选中，但历史关联继续保留。历史页面可显示其姓名和停用状态，避免配置记录失去上下文。

## 验证策略

用户已允许后续实现不再强制执行“先观察 RED”步骤，以减少子代理和 token 消耗；仍必须在提交前运行定向测试并读取结果。

定向验证至少覆盖：

- 每类可以保存多名员工
- 同一员工不能在同一类别重复关联
- 同一员工可以出现在不同类别
- 旧单员工 ID 能迁入关联表
- 回填服务重复执行不产生重复关联
- 无职位、已停用或已软删除员工不能作为新分配写入
- 列表和编辑表单从关联表读取全部员工
- 旧单员工 ID 和历史文本不被多选保存流程错误覆盖

## 非目标

- 不区分主处理人和协助处理人
- 不把员工 ID 以 JSON 或逗号字符串写入 `product_processing_craft`
- 本期不删除旧单员工 ID 和历史处理人文本字段
- 不把结算方式拆到员工级别
