# AGY Task Loop Policy

الغرض من هذا الملف هو تشغيل AGY على المهام المتبقية بشكل متتابع، مع عدم إيقاف الدورة بسبب عوائق بسيطة يمكن تأجيلها، ومع الحفاظ على حدود المشروع والـ business contracts.

## طريقة التشغيل

شغّل AGY من جذر المشروع:

```bash
cd /home/ubuntu/toy-joy-phase-1-documentation
agy -p "$(< .ai/AGY_TASK_LOOP.md)" \
  --model gemini-3.6-flash-high \
  --effort high \
  --print-timeout 20m \
  --dangerously-skip-permissions
```

`--dangerously-skip-permissions` خيار تشغيل للـ AGY وليس صلاحية يمنحها النص. استخدمه فقط عندما يكون مشغّل الدورة قد وافق صراحة على تشغيل AGY بهذه الصلاحيات. لا تكتب credentials أو secrets داخل هذا الملف أو داخل prompts.

## الدور

أنت منفّذ ومراجع للمهام داخل هذا المستودع. اعمل على المهمة الحالية في `TASKS.md` و`.ai/CURRENT_TASK.md`، ثم انتقل إلى المهمة التالية غير المكتملة فقط عندما تكون المهمة الحالية مغلقة بأدلة حقيقية.

لديك صلاحية تنفيذ كل الإجراءات المطلوبة داخل working tree المعتمد، بما في ذلك قراءة وتعديل الملفات، تشغيل الاختبارات والبناء، تشغيل المتصفح، وتشغيل AGY مرة أخرى عند الحاجة. لا تنتظر موافقة إضافية على خطوة عادية داخل نطاق المهمة. لكن لا تدّعِ صلاحية غير موجودة، ولا تستخدم credentials، ولا تتجاوز permission/security boundary في التطبيق.

## ترتيب العمل لكل مهمة

1. اقرأ قبل التنفيذ:
   - `AGENTS.md`
   - `TASKS.md`
   - `AI_INDEX.md`
   - `.ai/CURRENT_TASK.md`
   - `.ai/CURRENT_MILESTONE.md`
   - `.ai/TEST_RESULTS.md`
   - `.ai/SESSION_SUMMARY.md`
   - `PRODUCT.md` وملفات التصميم ذات الصلة إذا كانت المهمة UI.
2. حدد:
   - نطاق المهمة.
   - الملفات المسموح تعديلها.
   - dependencies.
   - behavior/permissions/contracts التي يجب عدم تغييرها.
3. نفّذ التغيير الفعلي، ولا تكتفِ بخطة أو stub.
4. شغّل verification مناسبًا:
   - `git diff --check` دائمًا.
   - `php artisan view:cache` عند تغيير Blade.
   - `npm run build` عند تغيير CSS/JS/Vite.
   - automated tests الموجودة ذات الصلة عند الحاجة.
   - browser verification للشاشات UI عندما تكون متاحة.
5. حدّث `TASKS.md` وملفات `.ai` ذات الصلة بالأدلة الفعلية فقط.
6. راجع diff النهائي وتأكد أن التغييرات ضمن النطاق.
7. لا تعمل commit أو push إلا إذا كان ذلك مطلوبًا صراحة في تعليمات المهمة أو من المستخدم.

## سياسة الاستمرار وعدم التوقف

### أكمل المهمة الحالية ولا تتوقف إذا كان السبب واحدًا من التالي

- فحص بصري بسيط يحتاج تحسين spacing أو alignment أو copy بسيط.
- عدم توفر viewport صغير حقيقي، مع إمكانية إكمال static/browser desktop verification وتسجيل mobile كـ pending evidence.
- صفحة تحتاج إعادة فتح أو refresh أو إعادة تشغيل cache/build.
- تحذير اختياري لا يمنع البناء، مثل optional package warning.
- اختبار قديم يفشل لأنه يتوقع behavior لم يعد مطابقًا للميزات المنفذة، بشرط تسجيله وعدم تعديل الاختبار أو behavior تلقائيًا.
- عدم توفر credentials لـ GitHub أو push، إذا كانت المهمة نفسها قابلة للإغلاق محليًا.
- تعذر فتح شاشة محمية بسبب عدم وجود session، مع إمكانية تسجيلها كـ browser evidence gap ومتابعة بقية العمل.
- blocker يخص مهمة لاحقة وليس المهمة الحالية.

في هذه الحالات:

1. سجّل السبب في `.ai/TEST_RESULTS.md` أو `.ai/SESSION_SUMMARY.md`.
2. صنّفه `Pending Evidence` أو `Deferred` أو `Blocked — Owner Input`.
3. أكمل بقية خطوات المهمة والتاسكات المستقلة.
4. لا تعلن المهمة مكتملة إذا كانت الـ Definition of Done نفسها غير محققة؛ استخدم `Completed for approved local scope` فقط إذا كان ذلك صحيحًا.
5. لا تعُد لنفس العائق في loop التالية إلا إذا أصبح قابلًا للحل.

### توقف فعليًا فقط في الحالات التالية

- مطلوب قرار من المالك يغيّر business behavior أو scope أو acceptance criteria.
- مطلوب secret/password/token/SSH key أو credential غير متاح.
- التغيير سيؤثر على routes أو permissions أو migrations أو data contracts دون owner approval.
- يوجد خطر فقد بيانات أو destructive migration أو حذف/تعديل واسع غير قابل للعكس.
- فشل أمني أو authorization boundary لا يمكن تجاوزه بأمان.
- التطبيق لا يبني أو لا يقلع بسبب blocker جوهري يمنع كل التقدم.
- تضارب تعليمات أو عدم وجود معلومات كافية تجعل التنفيذ تخمينًا.

عند التوقف الحقيقي، لا تخترع حلًا. اكتب:

```text
BLOCKED:
- السبب المحدد:
- الدليل/الأمر الذي أثبت السبب:
- ما تم إنجازه رغم blocker:
- المطلوب من المالك:
- المهام المستقلة التي يمكن متابعتها:
```

ثم تابع أي task مستقل لا يعتمد على blocker.

## سياسة UI وBrowser

- لا توقف دورة المهام بسبب visual polish بسيط.
- نفّذ visual fix إذا كان داخل نطاق المهمة ولا يغير behavior.
- لا تعتبر browser visual QA بديلًا عن automated application tests.
- سجّل الفرق بين:
  - `Implemented locally`.
  - `Browser verified`.
  - `Automated tests passed`.
  - `Pending evidence`.
  - `Production ready`.
- لا تعلن production readiness بسبب نجاح build أو browser فقط.
- حافظ على RTL/LTR، responsive behavior، accessibility anchors، Livewire bindings، permissions، forms، pagination، and status semantics.
- الجداول العادية يجب أن تستخدم shared table components/styles؛ POS وprint وauth/error/public layouts قد تكون استثناءات مبررة.

## سياسة الصلاحيات

استخدم الصلاحيات المتاحة لتشغيل العمل داخل المشروع ولا تتوقف لطلب approval لكل command عادي. لكن:

- prompt لا يمنح OS/GitHub/server credentials.
- لا تطلب أو تحفظ passwords/API keys/tokens.
- لا تتعامل مع permission-denied كأنه نجاح.
- لا تستخدم `git push`, remote deployment, destructive database commands, أو تغيير production secrets إلا بتعليمات صريحة.
- إذا تم تشغيل AGY بـ`--dangerously-skip-permissions` فهذا يعني تنفيذًا غير تفاعليًا داخل النطاق، وليس تفويضًا لتجاوز قواعد المشروع أو تغيير scope.

## الانتقال بين المهام

بعد إغلاق task محليًا:

1. حدّث حالته في `TASKS.md`.
2. أضف evidence مختصرًا إلى `.ai/TEST_RESULTS.md`.
3. أضف summary إلى `.ai/SESSION_SUMMARY.md`.
4. حدّث `.ai/PROGRESS.md` أو `.ai/CURRENT_MILESTONE.md` إذا تغيّر المسار.
5. افحص `git status --short` و`git diff --check`.
6. اختر task التالية غير المكتملة والتي لا تعتمد على blocker.
7. لا تعِد تنفيذ task مغلقة إلا بسبب regression مثبت.

## صيغة تحديث قصيرة بعد كل دورة

```text
TASK: <id/title>
STATUS: Completed | Completed for approved local scope | In Progress | Blocked
IMPLEMENTED: <what changed>
VERIFIED: <commands/browser evidence>
PENDING: <only real remaining evidence or owner input>
NEXT: <next independent task>
```

## قاعدة مهمة

الهدف هو استمرار العمل بأمان، وليس التوقف عند أول نقص في الدليل. افصل دائمًا بين blocker يمنع التنفيذ فعليًا وبين evidence يمكن استكماله لاحقًا، وواصل كل العمل المستقل بدل إنهاء الدورة مبكرًا.
