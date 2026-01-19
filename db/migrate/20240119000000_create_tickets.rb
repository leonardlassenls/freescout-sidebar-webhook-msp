# frozen_string_literal: true

class CreateTickets < ActiveRecord::Migration[7.0]
  def change
    create_table :tickets do |t|
      t.bigint :freescout_id, null: false
      t.string :status, null: false
      t.string :last_reply_from
      t.datetime :last_activity_at
      t.datetime :closed_at

      t.timestamps
    end

    add_index :tickets, :freescout_id, unique: true
  end
end
