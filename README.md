# Quick Central Login

第一方应用（开发者 Id=1）免确认直接发码的快速登录通道，回调地址额外带 `spicallogin` 标记。
核心不内置任何「免确认」策略，判定全部在本插件：第一方应用 + 用户没在 `/special/quick-login` 关掉。

- 钩子：`oauth.authorize.quick`（核心在授权请求判定阶段发，返回非空字符串即免确认直发并把它作为 `spicallogin` 的值）。
- 用户开关：`/special/quick-login`；默认开启，关掉的记录存 `quick_login_prefs` 表（没有记录 = 开启）。
- 需要核心 `>=1.4`（钩子在那版引入）。只部署在需要这个行为的实例上（如 id.caellab.com），不随包分发。

标记值沿用历史 slug `caellab-launch-plan-sister-project-quick-login`，接入方只判参数在不在，别比对值。
