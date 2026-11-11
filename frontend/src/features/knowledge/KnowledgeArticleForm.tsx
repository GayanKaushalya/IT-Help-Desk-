import { useEffect, useState, type FormEvent } from 'react'

import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Checkbox } from '@/components/ui/Checkbox'
import { FormField } from '@/components/ui/FormField'
import { Input } from '@/components/ui/Input'
import { Modal } from '@/components/ui/Modal'
import { Textarea } from '@/components/ui/Textarea'
import { useCreateKnowledgeArticle, useUpdateKnowledgeArticle } from '@/features/knowledge/useKnowledge'
import { useToast } from '@/hooks/useToast'
import { ApiError } from '@/types/api'
import type { KnowledgeArticle } from '@/types/models'

interface ArticleFormState {
  title: string
  summary: string
  topic: string
  body: string
  is_published: boolean
}

const EMPTY: ArticleFormState = {
  title: '',
  summary: '',
  topic: '',
  body: '',
  is_published: false,
}

export function KnowledgeArticleForm({
  isOpen,
  onClose,
  article,
  onSaved,
}: {
  isOpen: boolean
  onClose: () => void
  article?: KnowledgeArticle | null
  onSaved?: (article: KnowledgeArticle) => void
}) {
  const toast = useToast()
  const createArticle = useCreateKnowledgeArticle()
  const updateArticle = useUpdateKnowledgeArticle(article ? String(article.id) : '0')
  const [values, setValues] = useState<ArticleFormState>(EMPTY)
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  const editing = Boolean(article)
  const isSaving = createArticle.isPending || updateArticle.isPending

  useEffect(() => {
    if (!isOpen) return
    setValues(
      article
        ? {
            title: article.title,
            summary: article.summary ?? '',
            topic: article.topic ?? '',
            body: article.body ?? '',
            is_published: article.is_published,
          }
        : EMPTY,
    )
    setError(null)
    setFieldErrors({})
  }, [isOpen, article])

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault()
    setError(null)
    setFieldErrors({})

    const payload = {
      title: values.title.trim(),
      summary: values.summary.trim() || null,
      topic: values.topic.trim() || null,
      body: values.body.trim(),
      is_published: values.is_published,
    }

    try {
      const saved = editing
        ? await updateArticle.mutateAsync(payload)
        : await createArticle.mutateAsync(payload)
      toast.success(editing ? 'Article updated' : 'Article created', saved.title)
      onSaved?.(saved)
      onClose()
    } catch (caught) {
      if (caught instanceof ApiError) {
        if (caught.isValidationError) setFieldErrors(caught.fieldErrors)
        setError(caught.message)
      } else {
        setError('Unable to save the article right now.')
      }
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={editing ? 'Edit article' : 'New article'}
      description="Guides published here are visible to every signed-in employee."
      size="lg"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="knowledge-article-form" isLoading={isSaving}>
            {editing ? 'Save article' : 'Create article'}
          </Button>
        </>
      }
    >
      <form id="knowledge-article-form" className="space-y-4" onSubmit={handleSubmit}>
        {error && (
          <Alert tone="danger" title="Save failed">
            {error}
          </Alert>
        )}
        <FormField label="Title" required error={fieldErrors.title}>
          {({ id, hasError }) => (
            <Input
              id={id}
              hasError={hasError}
              value={values.title}
              onChange={(event) => setValues((current) => ({ ...current, title: event.target.value }))}
            />
          )}
        </FormField>
        <div className="grid gap-4 sm:grid-cols-2">
          <FormField label="Topic" error={fieldErrors.topic}>
            {({ id, hasError }) => (
              <Input
                id={id}
                hasError={hasError}
                value={values.topic}
                placeholder="Accounts, Network…"
                onChange={(event) => setValues((current) => ({ ...current, topic: event.target.value }))}
              />
            )}
          </FormField>
          <FormField label="Summary" error={fieldErrors.summary}>
            {({ id, hasError }) => (
              <Input
                id={id}
                hasError={hasError}
                value={values.summary}
                onChange={(event) => setValues((current) => ({ ...current, summary: event.target.value }))}
              />
            )}
          </FormField>
        </div>
        <FormField label="Body" required error={fieldErrors.body}>
          {({ id, hasError }) => (
            <Textarea
              id={id}
              rows={10}
              hasError={hasError}
              value={values.body}
              onChange={(event) => setValues((current) => ({ ...current, body: event.target.value }))}
            />
          )}
        </FormField>
        <Checkbox
          label="Published — visible to employees"
          checked={values.is_published}
          onChange={(event) => setValues((current) => ({ ...current, is_published: event.target.checked }))}
        />
      </form>
    </Modal>
  )
}
