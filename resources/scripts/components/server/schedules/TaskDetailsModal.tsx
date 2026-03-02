import React, { useContext, useEffect } from 'react';
import { Schedule, Task } from '@/api/server/schedules/getServerSchedules';
import { Field as FormikField, Form, Formik, FormikHelpers, useField } from 'formik';
import { ServerContext } from '@/state/server';
import createOrUpdateScheduleTask from '@/api/server/schedules/createOrUpdateScheduleTask';
import { httpErrorToHuman } from '@/api/http';
import Field from '@/components/elements/Field';
import FlashMessageRender from '@/components/FlashMessageRender';
import { boolean, number, object, string } from 'yup';
import useFlash from '@/plugins/useFlash';
import FormikFieldWrapper from '@/components/elements/FormikFieldWrapper';
import tw from 'twin.macro';
import Label from '@/components/elements/Label';
import { Textarea } from '@/components/elements/Input';
import { Button } from '@/components/elements/button/index';
import Select from '@/components/ui/select';
import ModalContext from '@/context/ModalContext';
import asModal from '@/hoc/asModal';
import FormikSwitch from '@/components/elements/FormikSwitch';
import { Activity, Play, Power, Save } from 'lucide-react';

interface Props {
    schedule: Schedule;
    // If a task is provided we can assume we're editing it. If not provided,
    // we are creating a new one.
    task?: Task;
}

interface Values {
    action: string;
    payload: string;
    timeOffset: string;
    continueOnFailure: boolean;
}

const schema = object().shape({
    action: string().required().oneOf(['command', 'power', 'backup']),
    payload: string().when('action', {
        is: (v) => v !== 'backup',
        then: string().required('A task payload must be provided.'),
        otherwise: string(),
    }),
    continueOnFailure: boolean(),
    timeOffset: number()
        .typeError('The time offset must be a valid number between 0 and 900.')
        .required('A time offset value must be provided.')
        .min(0, 'The time offset must be at least 0 seconds.')
        .max(900, 'The time offset must be less than 900 seconds.'),
});

const ActionListener = () => {
    const [{ value }, { initialValue: initialAction }] = useField<string>('action');
    const [, { initialValue: initialPayload }, { setValue, setTouched }] = useField<string>('payload');

    useEffect(() => {
        if (value !== initialAction) {
            setValue(value === 'power' ? 'start' : '');
            setTouched(false);
        } else {
            setValue(initialPayload || '');
            setTouched(false);
        }
    }, [value]);

    return null;
};

const TaskDetailsModal = ({ schedule, task }: Props) => {
    const { dismiss } = useContext(ModalContext);
    const { clearFlashes, addError } = useFlash();

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const appendSchedule = ServerContext.useStoreActions((actions) => actions.schedules.appendSchedule);
    const backupLimit = ServerContext.useStoreState((state) => state.server.data!.featureLimits.backups);

    useEffect(() => {
        return () => {
            clearFlashes('schedule:task');
        };
    }, []);

    const submit = (values: Values, { setSubmitting }: FormikHelpers<Values>) => {
        clearFlashes('schedule:task');
        if (backupLimit === 0 && values.action === 'backup') {
            setSubmitting(false);
            addError({
                message: "A backup task cannot be created when the server's backup limit is set to 0.",
                key: 'schedule:task',
            });
        } else {
            createOrUpdateScheduleTask(uuid, schedule.id, task?.id, values)
                .then((task) => {
                    let tasks = schedule.tasks.map((t) => (t.id === task.id ? task : t));
                    if (!schedule.tasks.find((t) => t.id === task.id)) {
                        tasks = [...tasks, task];
                    }

                    appendSchedule({ ...schedule, tasks });
                    dismiss();
                })
                .catch((error) => {
                    console.error(error);
                    setSubmitting(false);
                    addError({ message: httpErrorToHuman(error), key: 'schedule:task' });
                });
        }
    };

    return (
        <Formik
            onSubmit={submit}
            validationSchema={schema}
            initialValues={{
                action: task?.action || 'command',
                payload: task?.payload || '',
                timeOffset: task?.timeOffset.toString() || '0',
                continueOnFailure: task?.continueOnFailure || false,
            }}
        >
            {({ isSubmitting, values, setFieldValue }) => (
                <Form css={tw`m-0`}>
                    <FlashMessageRender byKey={'schedule:task'} css={tw`mb-4`} />
                    <h2 css={tw`mb-6 text-2xl text-[#f8f6ef]`}>{task ? 'Edit Task' : 'Create Task'}</h2>
                    <div css={tw`flex`}>
                        <div css={tw`mr-2 w-1/3`}>
                            <Label>Action</Label>
                            <ActionListener />
                            <FormikFieldWrapper name={'action'}>
                                    <Select
                                        title={'Choose Action'}
                                        defaultValue={values.action}
                                        onChange={(value) => {
                                            setFieldValue('action', value);
                                        }}
                                    data={[
                                        {
                                            id: 'action-command',
                                            label: 'Send Command',
                                            value: 'command',
                                            icon: <Activity size={14} />,
                                        },
                                        {
                                            id: 'action-power',
                                            label: 'Send Power Action',
                                            value: 'power',
                                            icon: <Power size={14} />,
                                        },
                                        {
                                            id: 'action-backup',
                                            label: 'Create Backup',
                                            value: 'backup',
                                            icon: <Save size={14} />,
                                        },
                                    ]}
                                />
                            </FormikFieldWrapper>
                        </div>
                        <div css={tw`flex-1 ml-6`}>
                            <Field
                                name={'timeOffset'}
                                label={'Time offset (in seconds)'}
                                description={
                                    'The amount of time to wait after the previous task executes before running this one. If this is the first task on a schedule this will not be applied.'
                                }
                            />
                        </div>
                    </div>
                    <div css={tw`mt-6`}>
                        {values.action === 'command' ? (
                            <div>
                                <Label>Payload</Label>
                                <FormikFieldWrapper name={'payload'}>
                                    <FormikField as={Textarea} name={'payload'} rows={6} />
                                </FormikFieldWrapper>
                            </div>
                        ) : values.action === 'power' ? (
                            <div>
                                <Label>Payload</Label>
                                <FormikFieldWrapper name={'payload'}>
                                    <Select
                                        title={'Power Action'}
                                        defaultValue={values.payload}
                                        onChange={(value) => {
                                            setFieldValue('payload', value);
                                        }}
                                        data={[
                                            {
                                                id: 'payload-start',
                                                label: 'Start the Server',
                                                value: 'start',
                                                icon: <Play size={14} />,
                                            },
                                            {
                                                id: 'payload-restart',
                                                label: 'Restart the Server',
                                                value: 'restart',
                                                icon: <Power size={14} />,
                                            },
                                            {
                                                id: 'payload-stop',
                                                label: 'Stop the Server',
                                                value: 'stop',
                                                icon: <Power size={14} />,
                                            },
                                            {
                                                id: 'payload-kill',
                                                label: 'Terminate the Server',
                                                value: 'kill',
                                                icon: <Power size={14} />,
                                            },
                                        ]}
                                    />
                                </FormikFieldWrapper>
                            </div>
                        ) : (
                            <div>
                                <Label>Ignored Files</Label>
                                <FormikFieldWrapper
                                    name={'payload'}
                                    description={
                                        'Optional. Include the files and folders to be excluded in this backup. By default, the contents of your .pteroignore file will be used. If you have reached your backup limit, the oldest backup will be rotated.'
                                    }
                                >
                                    <FormikField as={Textarea} name={'payload'} rows={6} />
                                </FormikFieldWrapper>
                            </div>
                        )}
                    </div>
                    <div css={tw`mt-6 rounded border border-[color:var(--border)] bg-[color:var(--background)] p-4 shadow-inner`}>
                        <FormikSwitch
                            name={'continueOnFailure'}
                            description={'Future tasks will be run when this task fails.'}
                            label={'Continue on Failure'}
                        />
                    </div>
                    <div css={tw`flex justify-end mt-6`}>
                        <Button type={'submit'} disabled={isSubmitting}>
                            {task ? 'Save Changes' : 'Create Task'}
                        </Button>
                    </div>
                </Form>
            )}
        </Formik>
    );
};

export default asModal<Props>()(TaskDetailsModal);
