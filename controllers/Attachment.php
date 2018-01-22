<?php

/*
 * Yamb - A module for NForum, a replacement of Mobile Module
 *
 * @auther    paper777 <wuzhyy@163.com>
 *
 */

class AttachmentController extends NF_YambController
{
    private $board;

    public function init()
    {
        parent::init();
        load(['model/board', 'model/article']);
    }

    public function indexAction()
    {
        $this->initRequest();
        $u = User::getInstance();
        if (isset($this->params['id'])) {
            $id = $this->params['id'];

            try {
                $article = Article::getInstance($id, $this->board);
                if (!$article->hasEditPerm($u)) {
                    return $this->fail('���²��ɱ༭');
                }
            } catch (ArticleNullException $e) {
                return $this->fail('δ�ҵ�����');
            }
            $attachments = $article->getAttList();
        } else {
            $attachments = Forum::listAttach();
        }

        return $this->success(
            compact('attachments')
        );
    }

    public function addAction()
    {
        if (!$this->getRequest()->isPost()) {
            $this->abort();
        }

        $this->initRequest();

        $u = User::getInstance();

        $isFile = false;
        if (isset($this->params['id'])) {
            $id = $this->params['id'];

            try {
                $article = Article::getInstance($id, $this->board);
                if (!$article->hasEditPerm($u)) {
                    return $this->fail('���²��ɱ༭');
                }
                $attachments = $article->getAttList();
            } catch (ArticleNullException $e) {
                return $this->fail('δ�ҵ�����');
            }
            $isFile = true;
        } else {
            $attachments = Forum::listAttach();
        }

        $totalSize = 0;
        foreach ($attachments as $att) {
            $totalSize += (int) $att['size'];
        }

        $upload = c('article');
        if (count($attachments) >= (int) $upload['att_num']) {
            return $this->fail('���������Ѵ��������');
        }

        if (isset($this->params['form']['file'])) {
            $errno = $this->params['form']['file']['error'];
        } else {
            $errno = UPLOAD_ERR_PARTIAL;
        }

        switch ($errno) {
        case UPLOAD_ERR_OK:
            $tmpFile = $this->params['form']['file']['tmp_name'];
            $tmpName = $this->params['form']['file']['name'];
            $tmpName = nforum_iconv($this->encoding, 'GBK', $tmpName);
            if (!is_uploaded_file($tmpFile)) {
                $msg = '����������';
                break;
            }
            if (($totalSize + filesize($tmpFile)) > (int) $upload['att_size']) {
                $msg = '������С��������';
                break;
            }

            try {
                if ($isFile) {
                    $article->addAttach($tmpFile, $tmpName);
                    $article = Article::getInstance($id, $this->board);
                } else {
                    Forum::addAttach($tmpFile, $tmpName);
                }

                return $this->success();
            } catch (ArticleNullException $e) {
                $msg = '����������';
            } catch (Exception $e) {
                $msg = '�ڲ�����'.$e->getMessage();
            }
            break;

        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
        case UPLOAD_ERR_PARTIAL:
            $msg = '������С��������';
            break;

        case UPLOAD_ERR_NO_FILE:
            $msg = '����������';
            break;

        default:
            $msg = '�ڲ�����';
            break;
        }

        return $this->fail($msg);
    }

    public function deleteAction()
    {
        $this->initRequest();
        $u = User::getInstance();

        if (!isset($this->params['form']['name'])) {
            return $this->fail('ȱ�ٸ�����');
        }

        $attName = strval($this->params['form']['name']);
        $attName = nforum_iconv($this->encoding, 'GBK', $attName);
        if (isset($this->params['id'])) {
            $id = $this->params['id'];

            try {
                $article = Article::getInstance($id, $this->board);
            } catch (Exception $e) {
                return $this->fail('����ʧ��');
            }
            if (!$article->hasEditPerm($u)) {
                return $this->fail('���²��ɱ༭');
            }

            $try = 0;
            do {
                $attNum = 0;
                // find the att
                foreach ($article->getAttList() as $k => $v) {
                    if ($v['name'] == $attName) {
                        $attNum = intval($k + 1);
                        break;
                    }
                }

                try {
                    $article->delAttach($attNum);
                } catch (Exception $e) {
                    $try++;
                    $attName = nforum_iconv('GBK', $this->encoding, $attName);
                    continue;
                }
                break;
            } while ($try <= 1);

            if ($try > 1) {
                return $this->fail('����ʧ��');
            }

            return $this->success();
        }

        try {
            Forum::delAttach($attName);
            $article = Forum::listAttach();
        } catch (Exception $e) {
            return $this->fail('����ʧ��');
        }

        return $this->success();
    }

    public function initRequest()
    {
        $name = $this->params['name'];
        $u = User::getInstance();

        try {
            $board = Board::getInstance($name);
            if (!$board->hasPostPerm($u) || !$board->isAttach()) {
                return $this->fail('���²��ɱ༭');
            }
        } catch (BoardNullException $e) {
            return $this->fail('δ�ҵ�����');
        }

        $this->board = $board;
        load('model/forum');
    }
}
