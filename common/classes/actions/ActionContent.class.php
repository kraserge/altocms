<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS
 * @Project URI: http://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 * Based on
 *   LiveStreet Engine Social Networking by Mzhelskiy Maxim
 *   Site: www.livestreet.ru
 *   E-mail: rus.engine@gmail.com
 *----------------------------------------------------------------------------
 */

/**
 * Экшен обработки УРЛа вида /content/ - управление своими топиками
 *
 * @package actions
 * @since 1.0
 */
class ActionContent extends Action {

    /**
     * Главное меню
     *
     * @var string
     */
    protected $sMenuHeadItemSelect = 'blog';

    /**
     * Меню
     *
     * @var string
     */
    protected $sMenuItemSelect = 'topic';

    /**
     * СубМеню
     *
     * @var string
     */
    protected $sMenuSubItemSelect = 'topic';

    /**
     * Текущий юзер
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent = null;

    /**
     * Текущий тип контента
     *
     * @var ModuleTopic_EntityContent|null
     */
    protected $oContentType = null;

    /**
     * Инициализация
     *
     */
    public function Init() {
        /**
         * Проверяем авторизован ли юзер
         */
        if (!$this->User_IsAuthorization()) {
            return parent::EventNotFound();
        }
        $this->oUserCurrent = $this->User_GetUserCurrent();
        /**
         * Устанавливаем дефолтный эвент
         */
        $this->SetDefaultEvent('add');
        /**
         * Устанавливаем title страницы
         */
        //$this->Viewer_AddHtmlTitle($this->Lang_Get('topic_title'));

        /**
         * Загружаем в шаблон JS текстовки
         */
        $this->Lang_AddLangJs(
            array('topic_photoset_photo_delete', 'topic_photoset_mark_as_preview',
                  'topic_photoset_photo_delete_confirm',
                  'topic_photoset_is_preview', 'topic_photoset_upload_choose'
            )
        );
    }

    /**
     * Регистрируем евенты
     *
     */
    protected function RegisterEvent() {

        $this->AddEventPreg('/^published$/i', '/^(page([1-9]\d{0,5}))?$/i', 'EventShowTopics');
        $this->AddEventPreg('/^saved$/i', '/^(page([1-9]\d{0,5}))?$/i', 'EventShowTopics');
        $this->AddEvent('edit', 'EventEdit');
        $this->AddEvent('delete', 'EventDelete');

        //Фото
        $this->AddEventPreg('/^photo$/i', '/^upload$/i', 'EventPhotoUpload'); // Загрузка изображения в фотосет
        $this->AddEventPreg('/^photo$/i', '/^delete$/i', 'EventPhotoDelete'); // Удаление изображения
        $this->AddEventPreg('/^photo$/i', '/^description$/i', 'EventPhotoDescription'); // Установка описания к фото
        $this->AddEventPreg('/^photo$/i', '/^getmore$/i', 'EventPhotoGetMore'); // Подгрузка изображений

        //Переход для топика с оригиналом
        $this->AddEvent('go', 'EventGo');

        $this->AddEventPreg('/^[\w\-\_]+$/i', '/^add$/i', array('EventAdd', 'add'));
    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    /**
     * Редактирование топика
     *
     */
    protected function EventEdit() {
        /**
         * Получаем номер топика из УРЛ и проверяем существует ли он
         */
        $sTopicId = $this->GetParam(0);
        if (!($oTopic = $this->Topic_GetTopicById($sTopicId))) {
            return parent::EventNotFound();
        }
        /*
         * Получаем тип контента
         */
        if (!$this->oContentType = $this->Topic_GetContentTypeByUrl($oTopic->getType())) {
            return parent::EventNotFound();
        }

        $this->Viewer_Assign('oContentType', $this->oContentType);
        /**
         * Если права на редактирование
         */
        if (!$this->ACL_IsAllowEditTopic($oTopic, $this->oUserCurrent)) {
            return parent::EventNotFound();
        }
        /**
         * Вызов хуков
         */
        $this->Hook_Run('topic_edit_show', array('oTopic' => $oTopic));

        $aBlogTypes = $this->Blog_GetAllowBlogTypes($this->oUserCurrent, 'write', true);
        $bPersonalBlog = in_array('personal', $aBlogTypes);

        /**
         * Загружаем переменные в шаблон
         */
        $this->Viewer_Assign('bPersonalBlog', $bPersonalBlog);
        /**
         * Загружаем переменные в шаблон
         */
        $this->Viewer_Assign('aBlogsAllow', $this->Blog_GetBlogsAllowByUser($this->oUserCurrent));
        $this->Viewer_Assign('bEditDisabled', $oTopic->getQuestionCountVote() == 0 ? false : true);
        $this->Viewer_AddHtmlTitle($this->Lang_Get('topic_topic_edit'));
        /**
         * Устанавливаем шаблон вывода
         */
        $this->SetTemplateAction('add');
        /**
         * Проверяем отправлена ли форма с данными(хотяб одна кнопка)
         */
        if (isset($_REQUEST['submit_topic_publish']) || isset($_REQUEST['submit_topic_save'])) {
            /**
             * Обрабатываем отправку формы
             */
            return $this->SubmitEdit($oTopic);
        } else {
            /**
             * Заполняем поля формы для редактирования
             * Только перед отправкой формы!
             */
            $_REQUEST['topic_title'] = $oTopic->getTitle();
            $_REQUEST['topic_text'] = $oTopic->getTextSource();
            $_REQUEST['topic_link_url'] = $oTopic->getLinkUrl();
            $_REQUEST['topic_tags'] = $oTopic->getTags();
            $_REQUEST['blog_id'] = $oTopic->getBlogId();
            $_REQUEST['topic_id'] = $oTopic->getId();
            $_REQUEST['topic_publish_index'] = $oTopic->getPublishIndex();
            $_REQUEST['topic_forbid_comment'] = $oTopic->getForbidComment();
            $_REQUEST['topic_main_photo'] = $oTopic->getPhotosetMainPhotoId();

            $_REQUEST['question_title'] = $oTopic->getQuestionTitle();
            $_REQUEST['answer'] = array();
            $aAnswers = $oTopic->getQuestionAnswers();
            foreach ($aAnswers as $aAnswer) {
                $_REQUEST['answer'][] = $aAnswer['text'];
            }

            foreach ($this->oContentType->getFields() as $oField) {
                if ($oTopic->getField($oField->getFieldId())) {
                    $sValue = $oTopic->getField($oField->getFieldId())->getValueSource();
                    if ($oField->getFieldType() == 'file') {
                        $sValue = unserialize($sValue);
                    }
                    $_REQUEST['fields'][$oField->getFieldId()] = $sValue;
                }
            }
            $sUrlMask = Router::GetTopicUrlMask();
            if (strpos($sUrlMask, '%topic_url%') === false) {
                // Нет в маске URL
                $_REQUEST['topic_url_before'] = $oTopic->getUrl($sUrlMask);
                $_REQUEST['topic_url'] = '';
                $_REQUEST['topic_url_after'] = '';
            } else {
                // В маске есть URL, вместо него нужно вставить <input>
                $aUrlMaskParts = explode('%topic_url%', $sUrlMask);
                $_REQUEST['topic_url_before'] = $oTopic->getUrl($aUrlMaskParts[0]);
                if (isset($aUrlMaskParts[1])) {
                    $_REQUEST['topic_url_after'] = $oTopic->getUrl($aUrlMaskParts[1], false);
                } else {
                    $_REQUEST['topic_url_after'] = '';
                }
                if ($oTopic->getTopicUrl()) {
                    $_REQUEST['topic_url'] = $oTopic->getTopicUrl();
                } else {
                    $_REQUEST['topic_url'] = $oTopic->getTitleTranslit();
                }
            }
            $_REQUEST['topic_url_short'] = $oTopic->getUrlShort();
        }
        $this->Viewer_Assign('aPhotos', $this->Topic_getPhotosByTopicId($oTopic->getId()));
    }

    /**
     * Удаление топика
     *
     */
    protected function EventDelete() {

        $this->Security_ValidateSendForm();

        // * Получаем номер топика из УРЛ и проверяем существует ли он
        $sTopicId = $this->GetParam(0);
        if (!($oTopic = $this->Topic_GetTopicById($sTopicId))) {
            return parent::EventNotFound();
        }

        // * проверяем есть ли право на удаление топика
        if (!$this->ACL_IsAllowDeleteTopic($oTopic, $this->oUserCurrent)) {
            return parent::EventNotFound();
        }

        // * Удаляем топик
        $this->Hook_Run('topic_delete_before', array('oTopic' => $oTopic));
        $this->Topic_DeleteTopic($oTopic);
        $this->Hook_Run('topic_delete_after', array('oTopic' => $oTopic));

        // * Перенаправляем на страницу со списком топиков из блога этого топика
        Router::Location($oTopic->getBlog()->getUrlFull());
    }

    /**
     * Добавление топика
     *
     */
    protected function EventAdd() {

        /**
         * Устанавливаем шаблон вывода
         */
        $this->SetTemplateAction('add');

        /**
         * Вызов хуков
         */
        $this->Hook_Run('topic_add_show');

        /*
         * Получаем тип контента
         */
        if (!$this->oContentType = $this->Topic_GetContentTypeByUrl($this->sCurrentEvent)) {
            return parent::EventNotFound();
        }

        $this->Viewer_Assign('oContentType', $this->oContentType);

        /**
         * Если тип контента не доступен текущему юзеру
         */
        if (!$this->oContentType->isAccessible()) {
            return parent::EventNotFound();
        }

        $aBlogTypes = $this->Blog_GetAllowBlogTypes($this->oUserCurrent, 'write', true);
        $bPersonalBlog = in_array('personal', $aBlogTypes);

        /**
         * Загружаем переменные в шаблон
         */
        $this->Viewer_Assign('bPersonalBlog', $bPersonalBlog);
        $this->Viewer_Assign('aBlogsAllow', $this->Blog_GetBlogsAllowByUser($this->oUserCurrent));
        $this->Viewer_Assign('bEditDisabled', false);
        $this->Viewer_AddHtmlTitle(
            $this->Lang_Get('topic_topic_create') . ' ' . mb_strtolower($this->oContentType->getContentTitle())
        );
        if (!is_numeric(getRequest('topic_id'))) {
            $_REQUEST['topic_id'] = '';
        }
        /**
         * Если нет временного ключа для нового топика, то генерируем; если есть, то загружаем фото по этому ключу
         */
        if ($sTargetTmp = $this->Session_GetCookie('ls_photoset_target_tmp')) {
            $this->Session_SetCookie('ls_photoset_target_tmp', $sTargetTmp, 'P1D');
            $this->Viewer_Assign('aPhotos', $this->Topic_getPhotosByTargetTmp($sTargetTmp));
        } else {
            $this->Session_SetCookie('ls_photoset_target_tmp', F::RandomStr(), 'P1D');
        }
        /**
         * Обрабатываем отправку формы
         */
        return $this->SubmitAdd();
    }

    /**
     * Выводит список топиков
     *
     */
    protected function EventShowTopics() {
        /**
         * Меню
         */
        $this->sMenuSubItemSelect = $this->sCurrentEvent;
        /**
         * Передан ли номер страницы
         */
        $iPage = $this->GetParamEventMatch(0, 2) ? $this->GetParamEventMatch(0, 2) : 1;
        /**
         * Получаем список топиков
         */
        $aResult = $this->Topic_GetTopicsPersonalByUser(
            $this->oUserCurrent->getId(), $this->sCurrentEvent == 'published' ? 1 : 0, $iPage,
            Config::Get('module.topic.per_page')
        );
        $aTopics = $aResult['collection'];
        /**
         * Формируем постраничность
         */
        $aPaging = $this->Viewer_MakePaging(
            $aResult['count'], $iPage, Config::Get('module.topic.per_page'), Config::Get('pagination.pages.count'),
            Router::GetPath('content') . $this->sCurrentEvent
        );
        /**
         * Загружаем переменные в шаблон
         */
        $this->Viewer_Assign('aPaging', $aPaging);
        $this->Viewer_Assign('aTopics', $aTopics);
        $this->Viewer_AddHtmlTitle($this->Lang_Get('topic_menu_' . $this->sCurrentEvent));
    }

    /**
     * Обработка добавления топика
     *
     */
    protected function SubmitAdd() {
        /**
         * Проверяем отправлена ли форма с данными (хотяб одна кнопка)
         */
        if (!F::isPost('submit_topic_publish') && !F::isPost('submit_topic_save')) {
            return false;
        }
        $oTopic = Engine::GetEntity('Topic');
        $oTopic->_setValidateScenario('topic');
        /**
         * Заполняем поля для валидации
         */
        $oTopic->setBlogId(getRequestStr('blog_id'));
        $oTopic->setTitle(strip_tags(getRequestStr('topic_title')));
        $oTopic->setTextSource(getRequestStr('topic_text'));
        $oTopic->setTags(getRequestStr('topic_tags'));
        $oTopic->setUserId($this->oUserCurrent->getId());
        $oTopic->setType($this->oContentType->getContentUrl());
        if ($this->oContentType->isAllow('link')) {
            $oTopic->setLinkUrl(getRequestStr('topic_link_url'));
        }
        $oTopic->setDateAdd(F::Now());
        $oTopic->setUserIp(F::GetUserIp());

        $sTopicUrl = $this->Topic_CorrectTopicUrl($oTopic->getTitleTranslit());
        $oTopic->setTopicUrl($sTopicUrl);
        /**
         * Проверка корректности полей формы
         */
        if (!$this->checkTopicFields($oTopic)) {
            return false;
        }
        /**
         * Определяем в какой блог делаем запись
         */
        $nBlogId = $oTopic->getBlogId();
        if ($nBlogId == 0) {
            $oBlog = $this->Blog_GetPersonalBlogByUserId($this->oUserCurrent->getId());
        } else {
            $oBlog = $this->Blog_GetBlogById($nBlogId);
        }
        /**
         * Если блог не определен, то выдаем предупреждение
         */
        if (!$oBlog) {
            $this->Message_AddErrorSingle($this->Lang_Get('topic_create_blog_error_unknown'), $this->Lang_Get('error'));
            return false;
        }
        /**
         * Проверяем права на постинг в блог
         */
        if (!$this->ACL_IsAllowBlog($oBlog, $this->oUserCurrent)) {
            $this->Message_AddErrorSingle($this->Lang_Get('topic_create_blog_error_noallow'), $this->Lang_Get('error'));
            return false;
        }
        /**
         * Проверяем разрешено ли постить топик по времени
         */
        if (isPost('submit_topic_publish') && !$this->ACL_CanPostTopicTime($this->oUserCurrent)) {
            $this->Message_AddErrorSingle($this->Lang_Get('topic_time_limit'), $this->Lang_Get('error'));
            return;
        }
        /**
         * Теперь можно смело добавлять топик к блогу
         */
        $oTopic->setBlogId($oBlog->getId());
        /**
         * Получаемый и устанавливаем разрезанный текст по тегу <cut>
         */
        list($sTextShort, $sTextNew, $sTextCut) = $this->Text_Cut($oTopic->getTextSource());

        $oTopic->setCutText($sTextCut);
        $oTopic->setText($this->Text_Parser($sTextNew));
        // Получаем ссылки, полученные при парсинге текста
        $oTopic->setTextLinks($this->Text_GetLinks());
        $oTopic->setTextShort($this->Text_Parser($sTextShort));

        /**
         * Варианты ответов
         */
        if ($this->oContentType->isAllow('question') && getRequestStr('question_title') && getRequest('answer', array())) {
            $oTopic->setQuestionTitle(strip_tags(getRequestStr('question_title')));
            $oTopic->clearQuestionAnswer();
            foreach (getRequest('answer', array()) as $sAnswer) {
                $oTopic->addQuestionAnswer((string)$sAnswer);
            }
        }

        /*
         * Если есть прикрепленные фото
         */
        if ($this->oContentType->isAllow('photoset') && $sTargetTmp = $this->Session_GetCookie('ls_photoset_target_tmp')) {
            $oTopic->setTargetTmp($sTargetTmp);
            if ($aPhotos = $this->Topic_getPhotosByTargetTmp($sTargetTmp)) {
                $oPhotoMain = $this->Topic_getTopicPhotoById(getRequestStr('topic_main_photo'));
                if (!$oPhotoMain || $oPhotoMain->getTargetTmp() != $sTargetTmp) {
                    $oPhotoMain = $aPhotos[0];
                }
                if ($oPhotoMain) {
                    $oTopic->setPhotosetMainPhotoId($oPhotoMain->getId());
                }
                $oTopic->setPhotosetCount(count($aPhotos));
                $aPhotosId = array();
                foreach($aPhotos as $oPhoto) {
                    $aPhotosId[] = $oPhoto->GetId();
                }
                $oTopic->setPhotosId($aPhotosId);
            } else {
                $oTopic->setPhotosetCount(0);
            }
        }

        /**
         * Публикуем или сохраняем
         */
        if (isset($_REQUEST['submit_topic_publish'])) {
            $oTopic->setPublish(1);
            $oTopic->setPublishDraft(1);
        } else {
            $oTopic->setPublish(0);
            $oTopic->setPublishDraft(0);
        }
        /**
         * Принудительный вывод на главную
         */
        $oTopic->setPublishIndex(0);
        if ($this->ACL_IsAllowPublishIndex($this->oUserCurrent)) {
            if (getRequest('topic_publish_index')) {
                $oTopic->setPublishIndex(1);
            }
        }
        /**
         * Запрет на комментарии к топику
         */
        $oTopic->setForbidComment(0);
        if (getRequest('topic_forbid_comment')) {
            $oTopic->setForbidComment(1);
        }

        // Разрешение/запрет индексации контента топика изначально - как у блога
        $oTopic->setTopicIndexIngnore($oBlog->GetBlogType()->GetIndexIgnore());

        /**
         * Запускаем выполнение хуков
         */
        $this->Hook_Run('topic_add_before', array('oTopic' => $oTopic, 'oBlog' => $oBlog));
        /**
         * Добавляем топик
         */
        if ($this->Topic_AddTopic($oTopic)) {
            $this->Hook_Run('topic_add_after', array('oTopic' => $oTopic, 'oBlog' => $oBlog));
            /**
             * Получаем топик, чтоб подцепить связанные данные
             */
            $oTopic = $this->Topic_GetTopicById($oTopic->getId());

            /**
             * Обновляем количество топиков в блоге
             */
            $this->Blog_RecalculateCountTopicByBlogId($oTopic->getBlogId());
            /**
             * Добавляем автора топика в подписчики на новые комментарии к этому топику
             */
            $this->Subscribe_AddSubscribeSimple(
                'topic_new_comment', $oTopic->getId(), $this->oUserCurrent->getMail(), $this->oUserCurrent->getId()
            );
            /**
             * Подписываем автора топика на обновления в трекере
             */
            if ($oTrack = $this->Subscribe_AddTrackSimple(
                'topic_new_comment', $oTopic->getId(), $this->oUserCurrent->getId()
            )
            ) {
                //если пользователь не отписался от обновлений топика
                if (!$oTrack->getStatus()) {
                    $oTrack->setStatus(1);
                    $this->Subscribe_UpdateTrack($oTrack);
                }
            }
            /**
             * Делаем рассылку всем, кто состоит в этом блоге
             */
            if ($oTopic->getPublish() == 1 && $oBlog->getType() != 'personal') {
                $this->Topic_SendNotifyTopicNew($oBlog, $oTopic, $this->oUserCurrent);
            }
            /**
             * Привязываем фото к ID топика
             * здесь нужно это делать одним запросом, а не перебором сущностей
             */
            if (isset($aPhotos) && count($aPhotos)) {
                foreach ($aPhotos as $oPhoto) {
                    $oPhoto->setTargetTmp(null);
                    $oPhoto->setTopicId($oTopic->getId());
                    $this->Topic_updateTopicPhoto($oPhoto);
                }
            }
            /**
             * Удаляем временную куку
             */
            $this->Session_DelCookie('ls_photoset_target_tmp');
            /**
             * Добавляем событие в ленту
             */
            $this->Stream_Write(
                $oTopic->getUserId(), 'add_topic', $oTopic->getId(),
                $oTopic->getPublish() && !$oBlog->getBlogType()->IsPrivate()
            );
            Router::Location($oTopic->getUrl());
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'));
            F::SysWarning('System Error');
            return Router::Action('error');
        }
    }

    /**
     * Обработка редактирования топика
     *
     * @param ModuleTopic_EntityTopic $oTopic
     *
     * @return mixed
     */
    protected function SubmitEdit($oTopic) {

        $oTopic->_setValidateScenario('topic');
        /**
         * Сохраняем старое значение идентификатора блога
         */
        $sBlogIdOld = $oTopic->getBlogId();
        /**
         * Заполняем поля для валидации
         */
        $oTopic->setBlogId(getRequestStr('blog_id'));
        $oTopic->setTitle(strip_tags(getRequestStr('topic_title')));
        $oTopic->setTextSource(getRequestStr('topic_text'));
        if ($this->oContentType->isAllow('link')) {
            $oTopic->setLinkUrl(getRequestStr('topic_link_url'));
        }
        $oTopic->setTags(getRequestStr('topic_tags'));
        $oTopic->setUserIp(F::GetUserIp());

        if ($this->oUserCurrent && $this->oUserCurrent->isAdministrator()) {
            if (getRequestStr('topic_url') && $oTopic->getTopicUrl() != getRequestStr('topic_url')) {
                $sTopicUrl = $this->Topic_CorrectTopicUrl(F::TranslitUrl(getRequestStr('topic_url')));
                $oTopic->setTopicUrl($sTopicUrl);
            }
        }
        /**
         * Проверка корректности полей формы
         */
        if (!$this->checkTopicFields($oTopic)) {
            return false;
        }
        /**
         * Определяем в какой блог делаем запись
         */
        $nBlogId = $oTopic->getBlogId();
        if ($nBlogId == 0) {
            $oBlog = $this->Blog_GetPersonalBlogByUserId($oTopic->getUserId());
        } else {
            $oBlog = $this->Blog_GetBlogById($nBlogId);
        }
        /**
         * Если блог не определен выдаем предупреждение
         */
        if (!$oBlog) {
            $this->Message_AddErrorSingle($this->Lang_Get('topic_create_blog_error_unknown'), $this->Lang_Get('error'));
            return false;
        }
        /**
         * Проверяем права на постинг в блог
         */
        if (!$this->ACL_IsAllowBlog($oBlog, $this->oUserCurrent)) {
            $this->Message_AddErrorSingle($this->Lang_Get('topic_create_blog_error_noallow'), $this->Lang_Get('error'));
            return false;
        }
        /**
         * Проверяем разрешено ли постить топик по времени
         */
        if (isPost('submit_topic_publish') && !$oTopic->getPublishDraft()
            && !$this->ACL_CanPostTopicTime(
                $this->oUserCurrent
            )
        ) {
            $this->Message_AddErrorSingle($this->Lang_Get('topic_time_limit'), $this->Lang_Get('error'));
            return;
        }
        $oTopic->setBlogId($oBlog->getId());
        /**
         * Получаемый и устанавливаем разрезанный текст по тегу <cut>
         */
        list($sTextShort, $sTextNew, $sTextCut) = $this->Text_Cut($oTopic->getTextSource());

        $oTopic->setCutText($sTextCut);
        $oTopic->setText($this->Text_Parser($sTextNew));

        // Получаем ссылки, полученные при парсинге текста
        $oTopic->setTextLinks($this->Text_GetLinks());
        $oTopic->setTextShort($this->Text_Parser($sTextShort));

        /**
         * изменяем вопрос/ответы только если еще никто не голосовал
         */
        if ($this->oContentType->isAllow('question') && getRequestStr('question_title') && getRequest('answer', array())
            && $oTopic->getQuestionCountVote() == 0
        ) {
            $oTopic->setQuestionTitle(strip_tags(getRequestStr('question_title')));
            $oTopic->clearQuestionAnswer();
            foreach (getRequest('answer', array()) as $sAnswer) {
                $oTopic->addQuestionAnswer((string)$sAnswer);
            }
        }
        /*
         * Если есть прикрепленные фото
         */
        if ($this->oContentType->isAllow('photoset') && $aPhotos = $oTopic->getPhotosetPhotos()) {
            $oPhotoMain = $this->Topic_getTopicPhotoById(getRequestStr('topic_main_photo'));
            if (!$oPhotoMain || $oPhotoMain->getTopicId() != $oTopic->getId()) {
                $oPhotoMain = $aPhotos[0];
            }
            $oTopic->setPhotosetMainPhotoId($oPhotoMain->getId());
            $oTopic->setPhotosetCount(count($aPhotos));
            // Сохраняем ID фотографий из фотосета
            $aPhotosId = array();
            foreach($aPhotos as $oPhoto) {
                $aPhotosId[] = $oPhoto->GetId();
            }
            $oTopic->setPhotosId($aPhotosId);
        } else {
            $oTopic->setPhotosetCount(0);
        }
        /**
         * Публикуем или сохраняем в черновиках
         */
        $bSendNotify = false;
        if (isset($_REQUEST['submit_topic_publish'])) {
            $oTopic->setPublish(1);
            if ($oTopic->getPublishDraft() == 0) {
                $oTopic->setPublishDraft(1);
                $oTopic->setDateAdd(F::Now());
                $bSendNotify = true;
            }
        } else {
            $oTopic->setPublish(0);
        }
        /**
         * Принудительный вывод на главную
         */
        if ($this->ACL_IsAllowPublishIndex($this->oUserCurrent)) {
            if (getRequest('topic_publish_index')) {
                $oTopic->setPublishIndex(1);
            } else {
                $oTopic->setPublishIndex(0);
            }
        }
        /**
         * Запрет на комментарии к топику
         */
        $oTopic->setForbidComment(0);
        if (getRequest('topic_forbid_comment')) {
            $oTopic->setForbidComment(1);
        }

        // Если запрет на индексацию не устанавливался вручную, то задаем, как у блога
        if (!$oTopic->getIndexIgnoreLock()) {
            $oTopic->setTopicIndexIngnore($oBlog->GetBlogType()->GetIndexIgnore());
        }

        $this->Hook_Run('topic_edit_before', array('oTopic' => $oTopic, 'oBlog' => $oBlog));
        /**
         * Сохраняем топик
         */
        if ($this->Topic_UpdateTopic($oTopic)) {
            $this->Hook_Run(
                'topic_edit_after', array('oTopic' => $oTopic, 'oBlog' => $oBlog, 'bSendNotify' => &$bSendNotify)
            );
            /**
             * Обновляем данные в комментариях, если топик был перенесен в новый блог
             */
            if ($sBlogIdOld != $oTopic->getBlogId()) {
                $this->Comment_UpdateTargetParentByTargetId($oTopic->getBlogId(), 'topic', $oTopic->getId());
                $this->Comment_UpdateTargetParentByTargetIdOnline($oTopic->getBlogId(), 'topic', $oTopic->getId());
            }
            /**
             * Обновляем количество топиков в блоге
             */
            if ($sBlogIdOld != $oTopic->getBlogId()) {
                $this->Blog_RecalculateCountTopicByBlogId($sBlogIdOld);
            }
            $this->Blog_RecalculateCountTopicByBlogId($oTopic->getBlogId());
            /**
             * Добавляем событие в ленту
             */
            $this->Stream_Write(
                $oTopic->getUserId(), 'add_topic', $oTopic->getId(),
                $oTopic->getPublish() && !$oBlog->getBlogType()->IsPrivate()
            );
            /**
             * Рассылаем о новом топике подписчикам блога
             */
            if ($bSendNotify) {
                $this->Topic_SendNotifyTopicNew($oBlog, $oTopic, $oTopic->getUser());
            }
            if (!$oTopic->getPublish() && !$this->oUserCurrent->isAdministrator()
                && $this->oUserCurrent->getId() != $oTopic->getUserId()
            ) {
                Router::Location($oBlog->getUrlFull());
            }
            Router::Location($oTopic->getUrl());
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'));
            F::SysWarning('System Error');
            return Router::Action('error');
        }
    }

    /**
     * AJAX подгрузка следующих фото
     *
     */
    protected function EventPhotoGetMore() {
        /**
         * Устанавливаем формат Ajax ответа
         */
        $this->Viewer_SetResponseAjax('json');
        /**
         * Существует ли топик
         */
        $oTopic = $this->Topic_getTopicById(getRequestStr('topic_id'));
        if (!$oTopic || !getRequest('last_id')) {
            $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            F::SysWarning('System Error');
            return false;
        }
        /**
         * Получаем список фото
         */
        $aPhotos = $oTopic->getPhotosetPhotos(getRequestStr('last_id'), Config::Get('module.topic.photoset.per_page'));
        $aResult = array();
        if (count($aPhotos)) {
            /**
             * Формируем данные для ajax ответа
             */
            foreach ($aPhotos as $oPhoto) {
                $aResult[] = array(
                    'id'          => $oPhoto->getId(),
                    'path_thumb'  => $oPhoto->getWebPath('50crop'),
                    'path'        => $oPhoto->getWebPath(),
                    'description' => $oPhoto->getDescription(),
                );
            }
            $this->Viewer_AssignAjax('photos', $aResult);
        }
        $this->Viewer_AssignAjax('bHaveNext', count($aPhotos) == Config::Get('module.topic.photoset.per_page'));
    }

    /**
     * AJAX удаление фото
     *
     */
    protected function EventPhotoDelete() {

        // * Устанавливаем формат Ajax ответа
        $this->Viewer_SetResponseAjax('json');

        // * Проверяем авторизован ли юзер
        if (!$this->User_IsAuthorization()) {
            $this->Message_AddErrorSingle($this->Lang_Get('not_access'), $this->Lang_Get('error'));
            return false;
        }

        // * Поиск фото по id
        $oPhoto = $this->Topic_getTopicPhotoById($this->GetPost('id'));
        if ($oPhoto) {
            if ($oPhoto->getTopicId()) {

                // * Проверяем права на топик
                $oTopic = $this->Topic_GetTopicById($oPhoto->getTopicId());
                if ($oTopic && $this->ACL_IsAllowEditTopic($oTopic, $this->oUserCurrent)) {
                    $this->Topic_deleteTopicPhoto($oPhoto);

                    // * Если удаляем главную фотографию. топика, то её необходимо сменить
                    if ($oPhoto->getId() == $oTopic->getPhotosetMainPhotoId() && $oTopic->getPhotosetCount() > 1) {
                        $aPhotos = $oTopic->getPhotosetPhotos(0, 1);
                        $oTopic->setPhotosetMainPhotoId($aPhotos[0]->getId());
                    } elseif ($oTopic->getPhotosetCount() == 1) {
                        $oTopic->setPhotosetMainPhotoId(null);
                    }
                    $oTopic->setPhotosetCount($oTopic->getPhotosetCount() - 1);
                    $this->Topic_UpdateTopic($oTopic);
                    $this->Message_AddNotice(
                        $this->Lang_Get('topic_photoset_photo_deleted'), $this->Lang_Get('attention')
                    );

                    return;
                }
            } else {
                $this->Topic_DeleteTopicPhoto($oPhoto);
                $this->Message_AddNotice($this->Lang_Get('topic_photoset_photo_deleted'), $this->Lang_Get('attention'));
                return;
            }
        }
        $this->Message_AddNotice(
            $this->Lang_Get('topic_photoset_photo_deleted'), $this->Lang_Get('attention')
        );
        /*
        $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
        F::SysWarning('System Error');
        */
    }

    /**
     * AJAX установка описания фото
     *
     */
    protected function EventPhotoDescription() {
        /**
         * Устанавливаем формат Ajax ответа
         */
        $this->Viewer_SetResponseAjax('json');
        /**
         * Проверяем авторизован ли юзер
         */
        if (!$this->User_IsAuthorization()) {
            $this->Message_AddErrorSingle($this->Lang_Get('not_access'), $this->Lang_Get('error'));
            return Router::Action('error');
        }
        /**
         * Поиск фото по id
         */
        $oPhoto = $this->Topic_getTopicPhotoById(getRequestStr('id'));
        if ($oPhoto) {
            if ($oPhoto->getTopicId()) {
                // проверяем права на топик
                $oTopic = $this->Topic_GetTopicById($oPhoto->getTopicId());
                if ($oTopic && $this->ACL_IsAllowEditTopic($oTopic, $this->oUserCurrent)) {
                    $oPhoto->setDescription(htmlspecialchars(strip_tags(getRequestStr('text'))));
                    $this->Topic_updateTopicPhoto($oPhoto);
                }
            } else {
                $oPhoto->setDescription(htmlspecialchars(strip_tags(getRequestStr('text'))));
                $this->Topic_updateTopicPhoto($oPhoto);
            }
        }
    }

    /**
     * AJAX загрузка фоток
     *
     * @return bool
     */
    protected function EventPhotoUpload() {

        // * Устанавливаем формат Ajax ответа. В зависимости от типа загрузчика устанавливается тип ответа
        if (getRequest('is_iframe')) {
            $this->Viewer_SetResponseAjax('jsonIframe', false);
        } else {
            $this->Viewer_SetResponseAjax('json');
        }

        // * Проверяем авторизован ли юзер
        if (!$this->User_IsAuthorization()) {
            $this->Message_AddErrorSingle($this->Lang_Get('not_access'), $this->Lang_Get('error'));
            return false;
        }

        // * Файл был загружен?
        $aUploadedFile = $this->GetUploadedFile('Filedata');
        if (!$aUploadedFile) {
            $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            F::SysWarning('System Error');
            return false;
        }

        $iTopicId = getRequestStr('topic_id');
        $sTargetId = null;

        // Если от сервера не пришёл ID топика, то пытаемся определить временный код для нового топика.
        // Если и его нет, то это ошибка
        if (!$iTopicId) {
            $sTargetId = $this->Session_GetCookie('ls_photoset_target_tmp');
            if (!$sTargetId) {
                $sTargetId = getRequestStr('ls_photoset_target_tmp');
            }
            if (!$sTargetId) {
                $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                F::SysWarning('System Error');
                return false;
            }
            $iCountPhotos = $this->Topic_getCountPhotosByTargetTmp($sTargetId);
        } else {
            // * Загрузка фото к уже существующему топику
            $oTopic = $this->Topic_getTopicById($iTopicId);
            if (!$oTopic || !$this->ACL_IsAllowEditTopic($oTopic, $this->oUserCurrent)) {
                $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                F::SysWarning('System Error');
                return false;
            }
            $iCountPhotos = $this->Topic_getCountPhotosByTopicId($iTopicId);
        }

        // * Максимальное количество фото в топике
        if ($iCountPhotos >= Config::Get('module.topic.photoset.count_photos_max')) {
            $this->Message_AddError(
                $this->Lang_Get(
                    'topic_photoset_error_too_much_photos',
                    array('MAX' => Config::Get('module.topic.photoset.count_photos_max'))
                ), $this->Lang_Get('error')
            );
            return false;
        }

        // * Максимальный размер фото
        if (filesize($aUploadedFile['tmp_name']) > Config::Get('module.topic.photoset.photo_max_size') * 1024) {
            $this->Message_AddError(
                $this->Lang_Get(
                    'topic_photoset_error_bad_filesize',
                    array('MAX' => Config::Get('module.topic.photoset.photo_max_size'))
                ), $this->Lang_Get('error')
            );
            return false;
        }

        // * Загружаем файл
        $sFile = $this->Topic_UploadTopicPhoto($aUploadedFile);
        if ($sFile) {
            // * Создаем фото
            $oPhoto = Engine::GetEntity('Topic_TopicPhoto');
            $oPhoto->setPath($sFile);
            if ($iTopicId) {
                $oPhoto->setTopicId($iTopicId);
            } else {
                $oPhoto->setTargetTmp($sTargetId);
            }
            if ($oPhoto = $this->Topic_AddTopicPhoto($oPhoto)) {
                // * Если топик уже существует (редактирование), то обновляем число фотографий в нём
                if (isset($oTopic)) {
                    $oTopic->setPhotosetCount($oTopic->getPhotosetCount() + 1);
                    $this->Topic_UpdateTopic($oTopic);
                }

                $this->Viewer_AssignAjax('file', $oPhoto->getWebPath('100crop'));
                $this->Viewer_AssignAjax('id', $oPhoto->getId());
                $this->Message_AddNotice($this->Lang_Get('topic_photoset_photo_added'), $this->Lang_Get('attention'));
            } else {
                $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                F::SysWarning('System Error');
            }
        } else {
            $sMsg = $this->Topic_UploadPhotoError();
            if (!$sMsg) {
                $sMsg = $this->Lang_Get('system_error');
            }
            $this->Message_AddError($sMsg, $this->Lang_Get('error'));
        }
    }

    /**
     * Переход по ссылке с подсчетом количества переходов
     *
     */
    protected function EventGo() {
        /**
         * Получаем номер топика из УРЛ и проверяем существует ли он
         */
        $sTopicId = $this->GetParam(0);
        if (!($oTopic = $this->Topic_GetTopicById($sTopicId)) || !$oTopic->getPublish()) {
            return parent::EventNotFound();
        }
        /**
         * проверяем есть ли ссылка на оригинал
         */
        if (!$oTopic->getLinkUrl()) {
            return parent::EventNotFound();
        }
        /**
         * увелививаем число переходов по ссылке
         */
        $oTopic->setLinkCountJump($oTopic->getLinkCountJump() + 1);
        $this->Topic_UpdateTopic($oTopic);
        /**
         * собственно сам переход по ссылке
         */
        Router::Location($oTopic->getLinkUrl());
    }

    /*
     * Обработка дополнительных полей
     */
    public function processFields($oTopic) {
    }

    /**
     * Проверка полей формы
     *
     * @param $oTopic
     *
     * @return bool
     */
    protected function checkTopicFields($oTopic) {

        $this->Security_ValidateSendForm();

        $bOk = true;
        /**
         * Валидируем топик
         */
        if (!$oTopic->_Validate()) {
            $this->Message_AddError($oTopic->_getValidateError(), $this->Lang_Get('error'));
            $bOk = false;
        }
        /**
         * Выполнение хуков
         */
        $this->Hook_Run('check_topic_fields', array('bOk' => &$bOk));

        return $bOk;
    }

    /**
     * При завершении экшена загружаем необходимые переменные
     *
     */
    public function EventShutdown() {

        $this->Viewer_Assign('sMenuHeadItemSelect', $this->sMenuHeadItemSelect);
        $this->Viewer_Assign('sMenuItemSelect', $this->sMenuItemSelect);
        $this->Viewer_Assign('sMenuSubItemSelect', $this->sMenuSubItemSelect);
    }

}

// EOF