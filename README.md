# P1 WordPress 主题

P1 是基于经典 U5 设计持续重构的 WordPress 主题，界面文案使用简体中文。

- 主题版本：`0.0.1`
- WordPress：`7.1` 或更新版本
- PHP：`8.5` 或更新版本
- 作者：[西风](https://xifeng.net)

## 主要功能

- 多套配色、自定义字体、页头与页面背景，以及渐变菜单。
- 统一的页面标题与分类、关键词、搜索结果列表。
- 文章目录、滚动导航、阅读时间、阅读量、单向点赞、链接复制和代码高亮。
- 评论回复合并在同一个气泡内，支持表情、访客信息保存和盖楼统计。
- 独立说说页面：便签布局、关键词徽章、地点、图片预览、发布、编辑、回收站与恢复。
- 归档热力图、订阅动态、友情链接列表和关于页面。
- 最近留言访客头像，以及 GitHub 每日贡献柱形图。
- 主题内置 Passkey 登录与管理，登录页面使用主题配色。
- 主题设置页面包含外观、写作、AI 摘要、评论与访客、页脚及统计代码等选项。
- 内部页面导航和评论提交支持局部更新；部分基础表单保留普通提交方式。

## 安装

1. 下载仓库 ZIP 并解压。
2. 将主题目录命名为 `P1`，放入 WordPress 的 `wp-content/themes/`。
3. 在 WordPress 后台 **外观 → 主题** 中启用 P1。
4. 前往 **外观 → P1 主题设置** 配置主题。
5. 在 **外观 → 菜单** 中设置站点导航。

也可以将整理好的 `P1` 文件夹打包成 ZIP，通过后台 **上传主题** 安装。

## 页面与数据兼容

关于、归档、友情链接和订阅页面使用主题注册的页面模式。说说使用 `page-memos.php` 模板，并兼容已有 `/talks/`、`/memos/` 页面及 ShanYing 的说说模板设置。

说说的统一内容类型为 `talk`。主题会迁移旧的 `feng_talk`、`p1_note` 类型，并读取原有 `feng_talk_tag` 关键词、`_feng_talk_location` 地点和 `_feng_talk_images` 图片记录。文章分类图标兼容 P1 和 ShanYing 的相关设置。

发布说说支持最多 4000 字正文、4 个关键词和 4 张图片。单张图片上限为 5 MB 或服务器允许的更小值，图片长边不超过 4096 像素。图片保存为 WordPress 媒体附件。

Passkey 在支持 WebAuthn 的浏览器与安全访问环境中使用。启用主题后，需要在账户中注册自己的通行密钥；已有 WordPress 密码登录仍可使用。

## 外部服务

- 动态图标使用主题配置的 Font Awesome CDN；部分样式使用 Pro 图标，需要使用者具备相应授权和可用资源。
- 部分字体、头像、站点图标及节气挂件依赖外部服务。
- GitHub 工作记录根据主题设置中的 GitHub 个人主页获取。
- AI 摘要需要配置自己的服务商与 API 凭据，生成请求可能产生服务费用。
- 访客公开 IP 查询使用 `mypublicipnow.com`；网络不可用时采用主题中的回退处理。

API 凭据、账户信息、评论和文章等运行数据保存在 WordPress 数据库中，不包含在本仓库内。

## 开发结构

```text
functions.php          主题功能、设置、页面模式与请求处理
style.css              主题元数据、配色、组件与响应式样式
theme.json             WordPress 编辑器配置
page-memos.php         说说页面与发布表单
assets/js/app.js       前端交互
assets/js/admin.js     主题设置交互
assets/css/            后台样式
assets/lib/            内置 WebAuthn 库
assets/fonts/          本地字体与授权说明
assets/backgrounds/    页面背景
assets/header-backgrounds/  页头背景
```

直接维护主题内的 PHP、CSS 和 JavaScript 文件，无需额外构建步骤。

前台会将主题 CSS 与 WordPress 生成的区块、全局和图片尺寸样式合并，写入主题内固定的 `assets/css/style-bundle.css`。样式内容变化时覆盖此文件，并更新链接上的数字版本号以刷新浏览器缓存；目录不可写时保留原有样式加载方式。生成文件不提交到 Git，`style.css` 是维护的源文件。动态脚本配置放在页面属性中，由 `assets/js/app.js` 读取。自定义器预览保留 WordPress 的原始样式输出，以支持实时修改。

`screenshot.png` 目前沿用旧主题预览，并不代表当前界面。主题仍在迭代中，正式站点更新前建议先在自己的环境中预览。

## 第三方资源

- WebAuthn：基于 `lbuchs/WebAuthn` 的内置子集，MIT 授权与版权说明保留在 `assets/lib/webauthn.php`。
- 代码高亮：授权说明见 `assets/js/highlight.LICENSE`。
- Ioskeley Mono：来源及授权说明见 `assets/fonts/ioskeley-mono/`。
- 本地图标子集：来自 Font Awesome Free 7.3.1，图标遵循 CC BY 4.0。
- 内置背景图的生成说明见对应资源目录中的 README。
